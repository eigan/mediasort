[![pipeline status](https://gitlab.com/eigan/mediasort/badges/master/pipeline.svg)](https://gitlab.com/eigan/mediasort/commits/master)
[![coverage report](https://gitlab.com/eigan/mediasort/badges/master/coverage.svg)](https://gitlab.com/eigan/mediasort/commits/master)

## Mediasort

**Currently in BETA:** Please use `--link` option, and always keep backup.

A batch rename tool for media files (audio, video and images). Move, or create an hardlink, with
a new name based on meta information extracted from the file. 

- Keeps your media easy to navigate
- Ensure no duplicates
- Can be executed after each change in directories 

 * [Example](#example)
 * [Installation](#installation)
    * [Composer](#composer)
    * [Build from source](#build-from-source)
 * [Usage](#usage)
    * [Options](#options)
 * [About](#about)
    * [Step by step](#step-by-step)
    * [Speed](#speed)
    * [Date and time from files](#date-and-time-from-files)
    * [File name collision](#file-name-collision)

### Example

```
mediasort source/ destination/
```
- `destination` is optional.
- `--format=":year/:month/:date :time"` default format of new filenames

Common options
- `-r` for recursive
- `--link` for using hardlinks
- `-n` for no interaction (autoconfirm)
- `-q` quiet
- See [more options](#options)

##### Before

```
source
├── IMG_20170331_180220.jpg
├── IMG_20170802_183621.jpg
├── IMG_20170802_183630.jpg
├── IMG_20170802_183634.jpg
└── VID_20171002_084709.mp4
```


##### After

Files are moved into `destination/` (create hardlinks with `--link`)

```
destination
└── 2017
    ├── 03 - March
    │   └── 2017-03-31 18:02:20.jpg
    ├── 08 - August
    │   ├── 2017-08-02 18:36:22.jpg
    │   ├── 2017-08-02 18:36:30.jpg
    │   └── 2017-08-02 18:36:35.jpg
    └── 10 - October
        └── 2017-10-02 08:47:09.mp4
```


### Installation

#### Requirements
- PHP 7.0.24+
  - ext-exif. For precise meta information (dates), and more.
  - exit-phar For composer (build from source), or to execute phar file


#### Composer
```
composer global require eigan/mediasort
```

#### Build from source
```sh
git clone https://gitlab.com/eigan/mediasort.git
cd mediasort
composer install --no-dev

php -d="phar.readonly=0" bin/build.php
chmod 755 mediasort.phar
mv mediasort.phar /usr/local/bin/mediasort
```


### Usage
#### Options
```
--format            Reformat the path
                    Example: --format=":year/:month/:date :time" (default)
             
                    Possible formatters:
                        :original (original path)
                        :date (alias ":year-:monthnum-:day")
                        :time (alias ":hour::minute::second")
                        :month (alias ":monthnum - :monthname")
                        :year
                        :monthnum
                        :monthname
                        :day
                        :hour
                        :minute
                        :second
                        :ext (not needed, always appended)
                        :name (original filename)
                        :dirname (name of original parent directory)
                     
                    Exif is mostly used if available

-r, --recursive     Look for files recursively in source

--only              Only files with the given extensions
                    Example: --only="jpg,gif"
                    
--only-type         Only files with the given filetype
                    Example: --type="image,video,audio" (default)
                    
--link              Create hardlink instead of moving

-v                  Show additional information

-n                  Disable interaction (Will autoconfirm)

--ignore            Ignore certain file extensions
                    Example: --ignore="db,db-journal"
                    
--dry-run           Do not execute move/link

--no-exif           Do not read exif meta information
```
Note: shortcuts cannot be combined, `-nv` will not work. This is a limitation of the CLI library used.

### About

#### Step by step
- Takes two arguments
  - `source`: Read files from here
  - `destination` (optional): Directory to populate. If not set, uses `source`
- Takes several options, see list above

-  Start look for media files in source
  - Skip files if:
    - Not a media file
    - Filtered by options
    - Is in built in ignorelist:
      - .nomedia, @eaDir
  
  - Generate a name based on the `--format` option
  - Check if the generated name exists
    - Check if duplicate
    - append an available "index" to the name
    
  - Move or link the media file into destination

#### Speed
For a structure with 3494 files (41.6GB), it took 0.29 seconds.

#### Date and time from files
Date is retrieved from files in the following order:
- exif meta information
- Date in path matching pattern:
  - YYYYMMDD_HHMMSS
  - YYYY-MM-DD HH.mm.ss
  - YYYY-MM-DD HH:mm:ss
  - YYYYMMDDHHMMSS
  - YYYYMMDD-HHMMSS
- Use file modification date
  - The date might not always be correct!

#### File name collision
When a file is identical, it gets ignored, otherwise we append an index to the filename.

[![pipeline status](https://gitlab.com/eigan/mediasort/badges/master/pipeline.svg)](https://gitlab.com/eigan/mediasort/commits/master)
[![coverage report](https://gitlab.com/eigan/mediasort/badges/master/coverage.svg)](https://gitlab.com/eigan/mediasort/commits/master)

## Mediasort

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
    * [Speed](#speed)
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
└── VID_20170709_121346.mp4
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
#### Composer
```
composer global require eigan/mediasort
```

#### Build from source
```sh
git clone https://gitlab.com/eigan/mediasort.git
cd mediasort
composer install

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
```
Note: shortcuts cannot be combined, `-nv` will not work. This is a limitation of the CLI library used.

### About
#### Speed
For a structure with 5929 files (38.7GB), it took 0.46s.

#### File name collision
```
filename = destination/2017/image.jpg

if fileName exists in destination:
    if file identical:
       ignore
    else
        add fileName index
        # filename = destination/2017/image (1).jpg
```
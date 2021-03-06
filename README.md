![Tests](https://github.com/eigan/mediasort/workflows/CI/badge.svg)
[![Coverage](https://codecov.io/gh/eigan/mediasort/branch/master/graph/badge.svg)](https://codecov.io/gh/eigan/mediasort)

## Mediasort

A batch rename tool for media files (audio, video and images). Move, or create an hardlink, with
a new name based on meta information extracted from the file. 

![](mediasort.gif)

 * [Example](#example)
 * [Installation](#installation)
    * [Requirements](#requirements)
    * [Arch Linux](#arch-linux)
 * [Usage](#usage)
    * [Options](#options)
 * [About](#about)
    * [Speed](#speed)
    * [Date and time from files](#date-and-time-from-files)
    * [File name collision](#file-name-collision)
    * [Step by step (internal)](#step-by-step-internal)
 * [Tips](#tips)
    * [Remove empty directories](#remove-empty-directories)
    
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

See the [wiki page](https://github.com/eigan/mediasort/wiki/Installation).

#### Requirements
- PHP 7.0.24+
  - ext-exif. For precise meta information (dates), and more.
  - ext-phar For composer (build from source), or to execute phar file

#### Arch Linux
Mediasort is available through AUR: [mediasort](https://aur.archlinux.org/packages/mediasort/).

#### Composer (global)
```
composer global require eigan/mediasort
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
-vv                 Show even more info (result for all formatters)

-n                  Disable interaction (Will autoconfirm)

--ignore            Ignore certain file extensions
                    Example: --ignore="db,db-journal"
                    
--dry-run           Do not execute move/link

--no-exif           Do not read exif meta information

--log-path          Specify where to put mediasort.log
                    Default: null (no logging)
```
Note: shortcuts cannot be combined, `-nv` will not work. This is a limitation of the CLI library used.

### About

#### Date and time from files
Date is retrieved from files in the following order:
- exif meta information (image)
- id3 meta information (video/audio)
- Date in path matching pattern:
  - YYYYMMDD_HHMMSS
  - YYYY-MM-DD HH.mm.ss
  - YYYY-MM-DD HH:mm:ss
  - YYYYMMDDHHMMSS
  - YYYYMMDD-HHMMSS
 
 If no dates are found, then the format fails and file is skipped.

#### File name collision
When a file is identical, it gets ignored, otherwise we append an index to the filename.

#### Step by step (internal)
```
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
```

### Tips
#### Remove empty directories
```
find . -type d -empty -delete
```


### Todo
These are things I would like to do sometime, but I don't really need right now.
- Split code into more files.
- More formatters
  - `:type-s`
  - `:exif(ExifProp)`
  - `:path` full original path
- `--filter=":size>10 & :name~/regex/ & :weekday=monday`
- I18n
- Test Mac (travis) / Windows (tea-ci)
- symlink

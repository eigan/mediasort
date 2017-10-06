[![pipeline status](https://gitlab.com/eigan/prettytree/badges/master/pipeline.svg)](https://gitlab.com/eigan/prettytree/commits/master)
[![coverage report](https://gitlab.com/eigan/prettytree/badges/master/coverage.svg)](https://gitlab.com/eigan/prettytree/commits/master)

## prettytree

A batch rename tool for directories. 

### Example

After an import from camera, and you want to put these files into a more logical position.

```
prettytree source/ destination/ --format=":year/:month/:date :time"
```
- `destination` is optional.
- `:year`: Year created.
- `:month`: Alias for `:monthnumeric - :monthstring`.
- `:date`: Alias for `:year-:monthnumeric-:day`.
- Extension will be appended
- All date related information is extracted from exif if you have enabled `ext-exif`.
- See more formats below.


##### Current directory structure

```
source
├── IMG_20170331_180220.jpg
├── IMG_20170802_183621.jpg
├── IMG_20170802_183630.jpg
├── IMG_20170802_183634.jpg
└── VID_20170709_121346.mp4
```


##### After tool completed

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
```sh
git clone https://gitlab.com/eigan/prettytree.git
cd prettytree
composer install --dev

php -d="phar.readonly=0" vendor/bin/box build
chmod 755 prettytree.phar
mv prettytree.phar /usr/local/bin/prettytree
```


### Usage
#### Options
```
--format            Reformat the path
                    Example: --format=":year/:month/:name"
             
                    Possible formatters:
                        :original (original path)
                        :date (alias ":year-:monthnumeric-:day")
                        :time (alias ":hour::minute::second")
                        :month (alias ":monumeric - :monthstring")
                        :year
                        :monthnumeric
                        :monthstring
                        :day
                        :hour
                        :minute
                        :second
                        :ext (always appended)
                        :name (original filename)
                        :dirname (name of original parent directory)
                     
                    Exif is mostly used if available

-r, --recursive     Look for files recursively in source

--only              Only files with the given extensions
                    Example: --only="jpg,gif"
                    
--only-type         Only files with the given filetype
                    Example: --type="photo,video"
                    
--link              Create hardlink instead of moving

-v                  Show additional information

-n                  Disable interaction (Will autoconfirm)

--ignore            Ignore certain file extensions
                    Example: --ignore="db,db-journal"
                    
--dry-run           Do not execute move/link
    

```
Note: shortcuts cannot be combined, `-nv` will not work. This is a limitation of `symfony/console`.


### TODO
All of these before `1.0`

- More integration tests.
- Show information when completed (num skipped, extensions encountered, etc).

### Scenarios
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
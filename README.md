### prettytree

#### Installation
```
git clone https://gitlab.com/eigan/prettytree.git
cd prettytree
composer install --dev
box build

mv prettytree.phar /usr/local/bin
```


#### Usage
```
prettytree source destination --format=":original"
```

##### Options
```
--format            Reformat the path
                    Example: --format=":year/:month/:name:ext"
             
                    Possible formatters:
                        :original (default)
                        :year
                        :monthnumeric
                        :monthstring
                        :month
                        :day
                        :exifyear
                        :original
                        :ext
                        :name
 
--recursive, -r     Also move files in sub directories
--only              Only files with the given extensions
                    Example: --only="jpg,gif"
--link              Use hardlink instead of moving

-v                  Show additional information
-n                  Disable interaction (Will autoconfirm)
```
Note: shortcuts cannot be combined, `-nv` will not work. This is a limitation of `symfony/console`.



#### Scenarios
##### File name collision
```
filename = destination/2017/image.jpg

if fileName exists in destination:
    if file identical:
       ignore
    else
        add fileName index
        # filename = destination/2017/image (1).jpg
```
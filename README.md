### Cache Middleware (laravel)
#### Installation
Composer:

`"repositories": [
         {
             "type": "vcs",
             "url":  "git@github.com:klimis/cache-middleware.git"
         }
     ]`

`"klimis/cachemiddleware": "^1.0.1"`     
#### Usage
Set methods to be cached in Controllers. Add to any controller `protected $cache = ['getPage'];` to cache forever or `protected $cache = [protected  $cache = [
                                                                                                 'getPage' => 60
                                                                                             ]];` 
for cache with timeout


##### TODO
Move controller methods to middleware.

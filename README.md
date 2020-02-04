### Cache Middleware 
##### (For Laravel Controllers)
[![Generic badge](https://img.shields.io/badge/stable-1.0.1-<COLOR>.svg)](https://shields.io/)
[![Generic badge](https://img.shields.io/badge/licence-MIT-BROWN.svg)](https://shields.io/)

####Installation
Composer:

`"repositories": [
         {
             "type": "vcs",
             "url":  "git@github.com:klimis/cache-middleware.git"
         }
     ]`

`"klimis/cachemiddleware": "^1.0.1"`     
####Usage
Set methods to be cached in Controllers. Add to any controller `protected $cache = ['getPage'];` to cache forever or 
`protected  $cache = ['getPage' => 60] ;` for cache with timeout


##### TODO
Move controller methods to middleware.

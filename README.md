### Cache Middleware 
##### (For Laravel Controllers)
[![Build Status](https://travis-ci.org/klimis/cache-middleware.svg?branch=master)](https://travis-ci.org/klimis/cache-middleware)
[![Generic badge](https://img.shields.io/badge/stable-1.0.4-<COLOR>.svg)](https://github.com/klimis/cache-middleware/tree/1.0.4)
[![Generic badge](https://img.shields.io/badge/licence-MIT-BROWN.svg)](https://opensource.org/licenses/MIT)

#### Installation
Composer:

`"repositories": [
         {
             "type": "vcs",
             "url":  "git@github.com:klimis/cache-middleware.git"
         }
     ]`

`"klimis/cachemiddleware": "^1.0.4"`     
#### Usage
Set methods to be cached in Controllers. Add to any controller `protected $cache = ['getPage'];` to cache forever or 
`protected  $cache = ['getPage' => 60] ;` for cache with timeout


##### TODO
* Move controller methods to middleware.
* More Unit test
* Create job for cleaning cache 


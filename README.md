### Cache Middleware 
##### (For Laravel Controllers)
[![Build Status](https://travis-ci.org/klimis/cache-middleware.svg?branch=master)](https://travis-ci.org/klimis/cache-middleware)
[![Generic badge](https://img.shields.io/badge/stable-1.0.8-<COLOR>.svg)](https://github.com/klimis/cache-middleware/tree/1.0.8)
[![Generic badge](https://img.shields.io/badge/licence-MIT-BROWN.svg)](https://opensource.org/licenses/MIT)

#### Installation
Composer:
1. `"klimis/cachemiddleware": "^1.0"`
2. Register in Kernel.php.  Eg `'cache' => \Klimis\CacheMiddleware\Middleware\CacheMiddleware::class`   
#### Usage
* Set methods to be cached in Controllers. Add to any controller `protected $cache = ['getPage'];` to cache forever or 
`protected  $cache = ['getPage' => 60] ;` for cache with timeout
* Use header `Api-Disable-Cache = 1` to force disable cache for request

##### TODO
* Move controller methods to middleware.
* More Unit test
* Create job for cleaning cache 
* Hash key



### Cache Middleware 
##### (For Laravel Controllers)
[![Build Status](https://travis-ci.org/klimis/cache-middleware.svg?branch=master)](https://travis-ci.org/klimis/cache-middleware)
[![Generic badge](https://img.shields.io/badge/stable-1.0.9-<COLOR>.svg)](https://github.com/klimis/cache-middleware/tree/1.0.9)
[![Generic badge](https://img.shields.io/badge/licence-MIT-BROWN.svg)](https://opensource.org/licenses/MIT)

#### Installation
Composer:
1. `"klimis/cachemiddleware": "^1.0"`
2. Register in Kernel.php.  Eg `'cache' => \Klimis\CacheMiddleware\Middleware\CacheMiddleware::class`   
#### Usage
* Set methods to be cached in Controllers. Add to any controller `public $cache = ['getPage'];` to cache forever or 
`public  $cache = ['getPage' => 60] ;` for cache with timeout
* Use header `Api-Disable-Cache = 1` to force disable cache for request
* Check response header `X-Is-From-Coin-Cache` to check if response is coming from cache
* Set env `DISABLE_CACHE` to true to disable all cache

##### TODO
* Move controller methods to middleware.
* More Unit test
* Create job for cleaning cache 
* Hash key



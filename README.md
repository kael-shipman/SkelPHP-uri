# SkelPHP Uri

*NOTE: The Skel framework is an __experimental__ web applications framework that I've created as an exercise in various systems design concepts. While I do intend to use it regularly on personal projects, it was not necessarily intended to be a "production" framework, since I don't ever plan on providing extensive technical support (though I do plan on providing extensive documentation). It should be considered a thought experiment and it should be used at your own risk. Read more about its conceptual foundations at [my website](https://colors.kaelshipman.me/about/this-website).*

This is a sort of bridge Uri implementation. My research at the time of development was apparently a little slack and I didn't find the PSR-7 [`UriInterface` interface](https://github.com/php-fig/http-message/blob/master/src/UriInterface.php). Now that I've found that, I have to investigate the legitimacy of using and/or extending it, rather than defining my own implementation. Of course, using it would imply yet another dependency, which is kind of contrary to the point of Skel, but then again, the world does turn....

Regardless, for now, the Skel Uri class is an implemenation of the custom Skel Uri interface.

## Usage

Eventually, this package is intended to be loaded as a composer package. For now, though, because this is still in very active development, I currently use it via a git submodule:

```bash
cd ~/my-website
git submodule add git@github.com:kael-shipman/skelphp-uri.git app/dev-src/skelphp/uri
```

This allows me to develop it together with the website I'm building with it. For more on the (somewhat awkward and complex) concept of git submodules, see [this page](https://git-scm.com/book/en/v2/Git-Tools-Submodules).


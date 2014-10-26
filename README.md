S2
==

Small and fast CMS

Installation
==

Use the following commands to deploy latest development version of S2 and extensions.
```
git clone git@github.com:parpalak/s2.git
cd s2
git rm _extensions
git submodule add git@github.com:parpalak/s2-extensions.git _extensions
php composer.phar install
```

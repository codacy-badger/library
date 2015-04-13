A PHP library to generate a static website. WIP.
=============

[![Build Status](https://travis-ci.org/Narno/PHPoole-library.svg?branch=master)](https://travis-ci.org/Narno/PHPoole-library)
[![Code Coverage](https://scrutinizer-ci.com/g/Narno/PHPoole-library/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/Narno/PHPoole-library/?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/Narno/PHPoole-library/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/Narno/PHPoole-library/?branch=master)
[![Code Climate](https://codeclimate.com/github/Narno/PHPoole-library/badges/gpa.svg)](https://codeclimate.com/github/Narno/PHPoole-library)
[![Dependency Status](https://www.versioneye.com/user/projects/551b20523661f134fe0001eb/badge.svg?style=flat)](https://www.versioneye.com/user/projects/551b20523661f134fe0001eb)

_PHPoole-library_ is a static website generator built on PHP, inspired by [Jekyll](http://jekyllrb.com/) and [Hugo](http://gohugo.io/).

It converts [Markdown](http://daringfireball.net/projects/markdown/) files into a static HTML web site, with the help of [Twig](http://twig.sensiolabs.org), a flexible and fast template engine.

You can easily create a blog, a personal website, a simple corporate website, etc.

Features
--------

* No database, files only (host your site anywhere)
* Fully configurable (Through options and plugins system) _WIP_
* Flexible template engine ([Twig](http://twig.sensiolabs.org/doc/templates.html))
* Theme support
* Dynamic menu creation
* Configurable taxonomies (categories, tags, etc.)
* Paginator (for homepage, sections and taxonomy)

Requirements
------------

Please see the [composer.json](composer.json) file.

Installation
------------

### Manually

[Download](http://narno.org/PHPoole-library/phpoole-library.phar) the Phar (not up to date)

### Composer

Run the following command:

    $ composer require narno/phpoole-library:1.0.X-dev

Demo
----

Try the [demo](https://github.com/Narno/PHPoole-demo)

Usage
-----

### Overview

To create a new website, you need 3 things:
 * pages (content)
 * templates (layouts)
 * a build script (PHP)

Organize your content:
```
.
├─ content             <- Contains Mardown files
|  ├─ Blog             <- A section named "Blog"
|  |  └─ Post 1.md     <- A page in a section
|  └─ About.md         <- A page
├─ layouts             <- Contains Twig templates
|  ├─ _default         <- Contains default templates
|  |  ├─ list.html     <- Used by a node type 'list'
|  |  ├─ page.html     <- Used by a node type 'page'
|  |  ├─ taxonomy.html <- Used by a node type 'taxonomy'
|  |  └─ terms.html    <- Used by a node type 'terms'
|  ├─ index.html       <- Used by the node type 'homepage'
└─ static              <- Contains static files
```

Create a PHP script:
```php
<?php
require_once 'vendor/autoload.php'; // Composer
//require_once 'phar://phpoole-library.phar'; // Phar
use PHPoole\PHPoole;

PHPoole::create(
    './', // The source directory
    null, // The destination directory (the same as source)
    [     // Options array
        'site' => [
            'title'   => "My website",             // The Site title
            'baseurl' => 'http://localhost:8000/', // The Site base URL
        ],
    ]
)->build(); // Launch builder

exec('php -S localhost:8000 -t _site'); // Run a local server
```

The static website is created in _./_site_.

### Content

A content file is composed by a frontmatter (Yaml) and a body (Markdown).

#### Page example

```yml
---
title: "The title"
date: "2013-01-01"
myvar: "My varm"
---
_Markdown_ page content.
```

### Layouts

A layout is a Twig template.

#### Layout example

```html
<h1>{{ page.title }}</h1>
<span>{{ page.date|date("j M Y") }}</span>
<b>{{ page.content }}</p>
<b>{{ page.myvar }}</p>
```

### Options

PHP script options to define how to build the website.

#### Default options

```php
[
    'site' => [
        'title'       => 'PHPoole',
        'baseline'    => 'A PHPoole website',
        'baseurl'     => 'http://localhost:8000/', // php -S localhost:8000 -t _site/ >/dev/null
        'description' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.',
        'taxonomies'  => [
            'tags'       => 'tag',
            'categories' => 'category',
        ],
        'paginate' => [
            'max'  => 5,
            'path' => 'page',
        ],
    ],
    'content' => [
        'dir' => 'content',
        'ext' => 'md',
    ],
    'frontmatter' => [
        'format' => 'yaml',
    ],
    'body' => [
        'format' => 'md',
    ],
    'static' => [
        'dir' => 'static',
    ],
    'layouts' => [
        'dir' => 'layouts',
    ],
    'output' => [
        'dir'      => '_site',
        'filename' => 'index.html',
    ],
    'themes' => [
        'dir' => 'themes',
    ],
]
```

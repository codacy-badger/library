<?php
/*
 * Copyright (c) Arnaud Ligny <arnaud@ligny.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PHPoole\Renderer;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Class Twig.
 */
class Twig implements RendererInterface
{
    /**
     * @var \Twig_Environment
     */
    protected $twig;
    /**
     * @var string
     */
    protected $templatesDir;
    /**
     * @var string
     */
    protected $rendered;
    /**
     * @var Filesystem
     */
    protected $fs;
    /**
     * @var bool
     */
    protected $twigStrict = true;
    /**
     * @var bool
     */
    protected $twigDebug = true;
    /*
     * @var string|bool
     */
    protected $twigCache = false;

    /**
     * {@inheritdoc}
     */
    public function __construct($templatesPath, $options)
    {
        $loaderFS = new \Twig_Loader_Filesystem($templatesPath);
        $loaderArray = new \Twig_Loader_Array([
            'redirect.html.twig' => '<!DOCTYPE html>
<html>
<head lang="en">
    <link rel="canonical" href="{{ url(page.destination) }}"/>
    <meta http-equiv="content-type" content="text/html; charset=utf-8" />
    <meta http-equiv="refresh" content="0;url={{ url(page.destination) }}" />
</head>
</html>',
        ]);
        $loader = new \Twig_Loader_Chain([$loaderArray, $loaderFS]);
        $this->twig = new \Twig_Environment($loader,
            [
                'autoescape'       => false,
                'strict_variables' => $this->twigStrict,
                'debug'            => $this->twigDebug,
                'cache'            => $this->twigCache,
            ]
        );
        $this->twig->addExtension(new \Twig_Extension_Debug());
        $this->twig->addExtension(new TwigExtensionSorts());
        $this->twig->addExtension(new TwigExtensionFilters());
        $this->twig->addExtension(new TwigExtensionUrlize());
        $this->twig->addExtension(new TwigExtensionMinify($options['destPath']));
        $this->twig->getExtension('core')->setDateFormat($options['date']['format']);
        $this->twig->getExtension('core')->setTimezone($options['date']['timezone']);

        // excerpt filter
        $excerptFilter = new \Twig_SimpleFilter('excerpt', function ($string, $length = 450, $suffix = '…') {
            $str = trim(strip_tags($string));
            if (mb_strlen($str) > $length) {
                $string = mb_substr($string, 0, $length).$suffix;
            }

            return $string;
        });
        $this->twig->addFilter($excerptFilter);

        $this->fs = new Filesystem();
    }

    /**
     * {@inheritdoc}
     */
    public function addGlobal($name, $value)
    {
        $this->twig->addGlobal($name, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function render($template, $variables)
    {
        $this->rendered = $this->twig->render($template, $variables);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function save($pathname)
    {
        if (!is_dir($dir = dirname($pathname))) {
            $this->fs->mkdir($dir);
        }
        if (false !== @file_put_contents($pathname, $this->rendered)) {
            return true;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function validate($template)
    {
        try {
            $this->twig->parse($this->twig->tokenize($template));

            return true;
        } catch (\Twig_Error_Syntax $e) {
            return false;
        }
    }
}

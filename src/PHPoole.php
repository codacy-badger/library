<?php
/*
 * Copyright (c) Arnaud Ligny <arnaud@ligny.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PHPoole;

use Dflydev\DotAccessData\Data;
use PHPoole\Collection\CollectionInterface;
use PHPoole\Converter\Converter;
use PHPoole\Generator\Alias;
use PHPoole\Generator\GeneratorManager;
use PHPoole\Generator\Homepage;
use PHPoole\Generator\Section;
use PHPoole\Generator\Taxonomy;
use PHPoole\Page\Collection as PageCollection;
use PHPoole\Page\NodeTypeEnum;
use PHPoole\Page\Page;
use PHPoole\Plugin\PluginAwareTrait;
use PHPoole\Renderer\RendererInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Zend\EventManager\EventsCapableInterface;

/**
 * Class PHPoole.
 */
class PHPoole implements EventsCapableInterface
{
    use PluginAwareTrait;

    const VERSION = '1.1.x-dev';
    /**
     * Default options.
     *
     * @var array
     */
    protected static $defaultOptions = [
        'site' => [
            'title'       => 'PHPoole',
            'baseline'    => 'A PHPoole website',
            'baseurl'     => 'http://localhost:8000/',
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
    ];
    /**
     * Options.
     *
     * @var Data
     */
    protected $options;
    /**
     * Source directory.
     *
     * @var string
     */
    protected $sourceDir;
    /**
     * Destination directory.
     *
     * @var string
     */
    protected $destDir;
    /**
     * Content iterator.
     *
     * @var Finder
     */
    protected $contentIterator;
    /**
     * Pages collection.
     *
     * @var PageCollection
     */
    protected $pageCollection;
    /**
     * Site variables.
     *
     * @var array
     */
    protected $site;
    /**
     * Collection of site menus.
     *
     * @var Collection\CollectionInterface
     */
    protected $menus;
    /**
     * Collection of taxonomies menus.
     *
     * @var Collection\CollectionInterface
     */
    protected $taxonomies;
    /**
     * Twig renderer.
     *
     * @var RendererInterface
     */
    protected $renderer;
    /**
     * The theme name.
     *
     * @var null
     */
    protected $theme = null;
    /**
     * Symfony\Component\Filesystem.
     *
     * @var Filesystem
     */
    protected $fs;
    /**
     * @var \Closure
     */
    protected $messageCallback;
    /**
     * @var GeneratorManager
     */
    protected $generators;

    /**
     * PHPoole constructor.
     *
     * @param array $options
     */
    public function __construct($options = [], \Closure $messageCallback = null)
    {
        // backward compatibility
        $args = func_get_args();
        if (count($args) > 2) {
            $this->setSourceDir($args[0]);
            $this->setDestDir($args[1]);
            $options = $args[2];
        } else {
            $this->setSourceDir(null);
            $this->setDestDir(null);
        }

        $data = new Data(self::$defaultOptions);
        $data->import($options);
        $this->setOptions($data);

        $this->setMessageCallback($messageCallback);

        $this->fs = new Filesystem();
    }

    /**
     * Creates a new PHPoole instance.
     *
     * @return PHPoole
     */
    public static function create()
    {
        $class = new \ReflectionClass(get_called_class());

        return $class->newInstanceArgs(func_get_args());
    }

    /**
     * @param null $sourceDir
     *
     * @throws \Exception
     *
     * @return $this
     */
    public function setSourceDir($sourceDir = null)
    {
        if ($sourceDir === null) {
            $sourceDir = getcwd();
        }
        if (!is_dir($sourceDir)) {
            throw new \Exception(sprintf("'%s' is not a valid source directory.", $sourceDir));
        }

        $this->sourceDir = $sourceDir;

        return $this;
    }

    /**
     * @param null $destDir
     *
     * @throws \Exception
     *
     * @return $this
     */
    public function setDestDir($destDir = null)
    {
        if ($destDir === null) {
            $destDir = $this->sourceDir;
        }
        if (!is_dir($destDir)) {
            throw new \Exception(sprintf("'%s' is not a valid destination directory.", $destDir));
        }

        $this->destDir = $destDir;

        return $this;
    }

    /**
     * Set options.
     *
     * @param Data $data
     *
     * @return $this
     *
     * @see    getOptions()
     */
    public function setOptions(Data $data)
    {
        if ($this->options !== $data) {
            $this->options = $data;
            $this->trigger('options', $data->export());
        }

        return $this;
    }

    /**
     * Get options.
     *
     * @return Data
     *
     * @see    setOptions()
     */
    public function getOptions()
    {
        if (is_null($this->options)) {
            $this->setOptions(new Data());
        }

        return $this->options;
    }

    /**
     * return an option value.
     *
     * @param string $key
     * @param string $default
     *
     * @return array|mixed|null
     *
     * @see    getOptions()
     */
    public function getOption($key, $default = '')
    {
        return $this->getOptions()->get($key, $default);
    }

    /**
     * @param \Closure $messageCallback
     */
    public function setMessageCallback($messageCallback = null)
    {
        if ($messageCallback === null) {
            $messageCallback = function ($code, $message = '', $itemsCount = 0, $itemsMax = 0, $verbose = true) {
                switch ($code) {
                    case 'CREATE':
                    case 'CONVERT':
                    case 'GENERATE':
                    case 'RENDER':
                    case 'COPY':
                        printf("\n> %s\n", $message);
                        break;
                    case 'CREATE_PROGRESS':
                    case 'CONVERT_PROGRESS':
                    case 'GENERATE_PROGRESS':
                    case 'RENDER_PROGRESS':
                    case 'COPY_PROGRESS':
                        if ($itemsCount > 0 && $verbose !== false) {
                            $length = (int) (($itemsCount / $itemsMax) * 100);
                            printf("\r  %d%% (%u/%u) %s", $length, $itemsCount, $itemsMax, $message);
                        } else {
                            printf("\r  %s", $message);
                        }
                        break;
                }
            };
        }
        $this->messageCallback = $messageCallback;
    }

    /**
     * Builds a new website.
     */
    public function build()
    {
        // locates content
        $this->locateContent();
        // creates Pages collection from content
        $this->createPagesFromContent();
        // converts Pages content
        $this->convertPages();
        // generates virtual pages
        $this->generateVirtualPages();
        // generates menus
        $this->generateMenus();
        // rendering
        $this->renderPages();
        // copies static files
        $this->copyStatic();
    }

    protected function setupGenerators()
    {
        $this->generators = (new GeneratorManager())
            ->addGenerator(new Section(), 0)
            ->addGenerator(new Alias(), 10)
            ->addGenerator(new Taxonomy($this->getOptions()), 20)
            ->addGenerator(new Homepage($this->getOptions()), 30);
    }

    /**
     * Locates content.
     *
     * @see build()
     */
    protected function locateContent()
    {
        try {
            $dir = $this->sourceDir.'/'.$this->getOption('content.dir');
            $params = compact('dir');
            $this->triggerPre(__FUNCTION__, $params);
            $this->contentIterator = Finder::create()
                ->files()
                ->in($params['dir'])
                ->name('*.'.$this->getOption('content.ext'));
            $this->triggerPost(__FUNCTION__, $params);
            if (!$this->contentIterator instanceof Finder) {
                throw new \Exception(__FUNCTION__.': result must be an instance of Symfony\Component\Finder.');
            }
        } catch (\Exception $e) {
            $params = compact('dir', 'e');
            $this->triggerException(__FUNCTION__, $params);
            echo $e->getMessage()."\n";
        }
    }

    /**
     * Create Pages collection from content iterator.
     *
     * @see build()
     */
    protected function createPagesFromContent()
    {
        $this->pageCollection = new PageCollection();
        if (count($this->contentIterator) <= 0) {
            return;
        }
        call_user_func_array($this->messageCallback, ['CREATE', 'Creating pages']);
        $max = count($this->contentIterator);
        $count = 0;
        /* @var $file SplFileInfo */
        foreach ($this->contentIterator as $file) {
            $count++;
            /* @var $page Page */
            $page = (new Page($file))->parse();
            $this->pageCollection->add($page);
            $message = $page->getName();
            call_user_func_array($this->messageCallback, ['CREATE_PROGRESS', $message, $count, $max]);
        }
    }

    /**
     * Converts content of all pages.
     *
     * @see convertPage()
     * @see build()
     */
    protected function convertPages()
    {
        if (count($this->pageCollection) <= 0) {
            return;
        }
        call_user_func_array($this->messageCallback, ['CONVERT', 'Converting pages']);
        $max = count($this->pageCollection);
        $count = 0;
        $countError = 0;
        /* @var $page Page */
        foreach ($this->pageCollection as $page) {
            if (!$page->isVirtual()) {
                $count++;
                if (false !== $convertedPage = $this->convertPage($page, $this->getOption('frontmatter.format'))) {
                    $this->pageCollection->replace($page->getId(), $convertedPage);
                } else {
                    $countError++;
                }
                $message = $page->getName();
                call_user_func_array($this->messageCallback, ['CONVERT_PROGRESS', $message, $count - $countError, $max]);
            }
        }
    }

    /**
     * Converts page content:
     * * Yaml frontmatter to PHP array
     * * Mardown body to HTML.
     *
     * @param Page   $page
     * @param string $format
     *
     * @return Page
     */
    public function convertPage($page, $format = 'yaml')
    {
        // converts frontmatter
        try {
            $variables = (new Converter())->convertFrontmatter($page->getFrontmatter(), $format);
        } catch (\Exception $e) {
            $message = sprintf("Unable to convert frontmatter of '%s': %s\n", $page->getId(), $e->getMessage());
            call_user_func_array($this->messageCallback, ['CONVERT_PROGRESS', $message]);

            return false;
        }
        // converts body
        $html = (new Converter())
            ->convertBody($page->getBody());
        /*
         * Setting default page properties
         */
        if (!empty($variables['title'])) {
            $page->setTitle($variables['title']);
            unset($variables['title']);
        }
        if (!empty($variables['section'])) {
            $page->setSection($variables['section']);
            unset($variables['section']);
        }
        if (!empty($variables['date'])) {
            $page->setDate($variables['date']);
        }
        if (!empty($variables['permalink'])) {
            $page->setPermalink($variables['permalink']);
            unset($variables['permalink']);
        }
        if (!empty($variables['layout'])) {
            $page->setLayout($variables['layout']);
            unset($variables['layout']);
        }
        $page->setHtml($html);
        // setting page variables
        $page->setVariables($variables);

        return $page;
    }

    /**
     * Generates virtual pages.
     *
     * @see build()
     */
    protected function generateVirtualPages()
    {
        call_user_func_array($this->messageCallback, ['GENERATE', 'Generating pages']);
        $this->setupGenerators();
        /* @var $generatedPages CollectionInterface */
        $generatedPages = $this->generators->generate($this->pageCollection, $this->messageCallback);
        foreach ($generatedPages as $page) {
            $this->pageCollection->add($page);
        }
    }

    /**
     * Generates menus.
     *
     * @see build()
     */
    protected function generateMenus()
    {
        $this->menus = new Menu\Collection();

        /* @var $page Page */
        foreach ($this->pageCollection as $page) {
            if (!empty($page['menu'])) {
                // single
                /*
                 * ex:
                 * menu: main
                 */
                if (is_string($page['menu'])) {
                    $item = (new Menu\Entry($page->getId()))
                        ->setName($page->getTitle())
                        ->setUrl($page->getPermalink());
                    /* @var $menu Menu\Menu */
                    $menu = $this->menus->get($page['menu']);
                    $menu->add($item);
                }
                // multiple
                /*
                 * ex:
                 * menu:
                 *     main:
                 *         weight: 1000
                 *     other
                 */
                if (is_array($page['menu'])) {
                    foreach ($page['menu'] as $name => $value) {
                        $item = (new Menu\Entry($page->getId()))
                            ->setName($page->getTitle())
                            ->setUrl($page->getPermalink())
                            ->setWeight($value['weight']);
                        /* @var $menu Menu\Menu */
                        $menu = $this->menus->get($name);
                        $menu->add($item);
                    }
                }
            }
        }
        /*
         * Removing/adding/replacing menus entries from options array
         */
        if ($this->getOption('site.menu') !== '') {
            foreach ($this->getOption('site.menu') as $name => $entry) {
                /* @var $menu Menu\Menu */
                $menu = $this->menus->get($name);
                foreach ($entry as $property) {
                    if (isset($property['disabled']) && $property['disabled']) {
                        if (isset($property['id']) && $menu->has($property['id'])) {
                            $menu->remove($property['id']);
                        }
                        continue;
                    }
                    $item = (new Menu\Entry($property['id']))
                        ->setName($property['name'])
                        ->setUrl($property['url'])
                        ->setWeight($property['weight']);
                    $menu->add($item);
                }
            }
        }
    }

    /**
     * Pages rendering:
     * 1. Iterates Pages collection
     * 2. Applies Twig templates
     * 3. Saves rendered files.
     *
     * @see renderPage()
     * @see build()
     */
    protected function renderPages()
    {
        $paths = [];
        // prepares global site variables
        $this->site = array_merge(
            $this->getOption('site'),
            ['menus' => $this->menus],
            ['pages' => $this->pageCollection]
        );
        // prepares renderer
        if (!is_dir($this->sourceDir.'/'.$this->getOption('layouts.dir'))) {
            throw new \Exception(sprintf("'%s' is not a valid layouts directory", $this->getOption('layouts.dir')));
        } else {
            $paths[] = $this->sourceDir.'/'.$this->getOption('layouts.dir');
        }
        if ($this->isTheme()) {
            $paths[] = $this->sourceDir.'/'.$this->getOption('themes.dir').'/'.$this->theme.'/layouts';
        }
        $this->renderer = new Renderer\Twig($paths);
        // adds global variables
        $this->renderer->addGlobal('site', $this->site);
        $this->renderer->addGlobal('phpoole', [
            'url'       => 'http://narno.org/PHPoole-library/#v'.self::getVersion(),
            'version'   => self::getVersion(),
            'poweredby' => 'PHPoole-library v'.self::getVersion(),
        ]);

        // start rendering
        $dir = $this->destDir.'/'.$this->getOption('output.dir');
        $this->fs->mkdir($dir);
        call_user_func_array($this->messageCallback, ['RENDER', 'Rendering pages']);
        $max = count($this->pageCollection);
        $count = 0;
        /* @var $page Page */
        foreach ($this->pageCollection as $page) {
            $count++;
            $pathname = $this->renderPage($page, $dir);
            $message = $pathname;
            call_user_func_array($this->messageCallback, ['RENDER_PROGRESS', $message, $count, $max]);
        }
    }

    /**
     * Render a page.
     *
     * @param Page   $page
     * @param string $dir
     *
     * @throws \Exception
     *
     * @see renderPages()
     */
    protected function renderPage(Page $page, $dir)
    {
        $this->renderer->render($this->layoutFinder($page), [
            'page' => $page,
        ]);

        // force pathname of a none virtual node page
        if ($page->getName() == 'index') {
            $pathname = $dir.'/'.$page->getPath().'/'.$this->getOption('output.filename');
        // pathname of a page
        } else {
            if (empty(pathinfo($page->getPermalink(), PATHINFO_EXTENSION))) {
                $pathname = $dir.'/'.$page->getPermalink().'/'.$this->getOption('output.filename');
            } else {
                $pathname = $dir.'/'.$page->getPermalink();
            }
        }

        $pathname = preg_replace('#/+#', '/', $pathname); // remove unnecessary slashes
        $this->renderer->save($pathname);

        return $pathname;
    }

    /**
     * Copy static directory content to site root.
     *
     * @see build()
     */
    protected function copyStatic()
    {
        call_user_func_array($this->messageCallback, ['COPY', 'Copy static files']);
        $dir = $this->destDir.'/'.$this->getOption('output.dir');
        // copy theme static dir if exists
        if ($this->isTheme()) {
            $themeStaticDir = $this->sourceDir.'/'.$this->getOption('themes.dir').'/'.$this->theme.'/static';
            if ($this->fs->exists($themeStaticDir)) {
                $this->fs->mirror($themeStaticDir, $dir, null, ['override' => true]);
            }
        }
        // copy static dir if exists
        $staticDir = $this->sourceDir.'/'.$this->getOption('static.dir');
        if ($this->fs->exists($staticDir)) {
            $this->fs->mirror($staticDir, $dir, null, ['override' => true]);
        }
        call_user_func_array($this->messageCallback, ['COPY_PROGRESS', 'Done']);
    }

    /**
     * Uses a theme?
     * If yes, set $theme variable.
     *
     * @throws \Exception
     *
     * @return bool
     */
    protected function isTheme()
    {
        if ($this->theme !== null) {
            return true;
        }
        if ($this->getOption('theme') !== '') {
            $themesDir = $this->sourceDir.'/'.$this->getOption('themes.dir');
            if ($this->fs->exists($themesDir.'/'.$this->getOption('theme'))) {
                $this->theme = $this->getOption('theme');

                return true;
            }
            throw new \Exception(sprintf("Theme directory '%s' not found!", $themesDir));
        }

        return false;
    }

    /**
     * Layout file finder.
     *
     * @param Page $page
     *
     * @throws \Exception
     *
     * @return string
     */
    protected function layoutFinder(Page $page)
    {
        $layout = 'unknown';

        if ($page->getLayout() == 'redirect') {
            return $page->getLayout().'.html';
        }

        $layouts = $this->layoutFallback($page);

        // is layout exists in local layout dir?
        $layoutsDir = $this->sourceDir.'/'.$this->getOption('layouts.dir');
        foreach ($layouts as $layout) {
            if ($this->fs->exists($layoutsDir.'/'.$layout)) {
                return $layout;
            }
        }
        // is layout exists in layout theme dir?
        if ($this->isTheme()) {
            $themeDir = $this->sourceDir.'/'.$this->getOption('themes.dir').'/'.$this->theme.'/layouts';
            foreach ($layouts as $layout) {
                if ($this->fs->exists($themeDir.'/'.$layout)) {
                    return $layout;
                }
            }
        }
        throw new \Exception(sprintf("Layout '%s' not found for page '%s'!", $layout, $page->getId()));
    }

    /**
     * Layout fall-back.
     *
     * @param $page
     *
     * @return string[]
     *
     * @see layoutFinder()
     */
    protected function layoutFallback(Page $page)
    {
        switch ($page->getNodeType()) {
            case NodeTypeEnum::HOMEPAGE:
                $layouts = [
                    'index.html',
                    '_default/list.html',
                    '_default/page.html',
                ];
                break;
            case NodeTypeEnum::SECTION:
                $layouts = [
                    // 'section/$section.html'
                    '_default/section.html',
                    '_default/list.html',
                ];
                if ($page->getSection() !== null) {
                    $layouts = array_merge([sprintf('section/%s.html', $page->getSection())], $layouts);
                }
                break;
            case NodeTypeEnum::TAXONOMY:
                $layouts = [
                    // 'taxonomy/$singular.html'
                    '_default/taxonomy.html',
                    '_default/list.html',
                ];
                if ($page->getVariable('singular') !== null) {
                    $layouts = array_merge([sprintf('taxonomy/%s.html', $page->getVariable('singular'))], $layouts);
                }
                break;
            case NodeTypeEnum::TERMS:
                $layouts = [
                    // 'taxonomy/$singular.terms.html'
                    '_default/terms.html',
                ];
                if ($page->getVariable('singular') !== null) {
                    $layouts = array_merge([sprintf('taxonomy/%s.terms.html', $page->getVariable('singular'))], $layouts);
                }
                break;
            default:
                $layouts = [
                    // '$section/page.html'
                    // '$section/$layout.html'
                    // '$layout.html'
                    // 'page.html'
                    '_default/page.html',
                ];
                if ($page->getSection() !== null) {
                    $layouts = array_merge([sprintf('%s/page.html', $page->getSection())], $layouts);
                    if ($page->getLayout() !== null) {
                        $layouts = array_merge([sprintf('%s/%s.html', $page->getSection(), $page->getLayout())], $layouts);
                    }
                } else {
                    $layouts = array_merge(['page.html'], $layouts);
                    if ($page->getLayout() !== null) {
                        $layouts = array_merge([sprintf('%s.html', $page->getLayout())], $layouts);
                    }
                }
        }

        return $layouts;
    }

    /**
     * Return version.
     *
     * @return string
     */
    protected static function getVersion()
    {
        $version = self::VERSION;

        if (file_exists(__DIR__.'/../composer.json')) {
            $composer = json_decode(file_get_contents(__DIR__.'/../composer.json'), true);
            if (isset($composer['version'])) {
                $version = $composer['version'];
            }
        }

        return $version;
    }
}

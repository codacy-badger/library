<?php
/*
 * Copyright (c) Arnaud Ligny <arnaud@ligny.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PHPoole\Generator;

use PHPoole\Collection\Collection as PageCollection;
use PHPoole\Collection\Page\Page;

/**
 * Class TitleReplace.
 */
class TitleReplace extends AbstractGenerator implements GeneratorInterface
{
    /**
     * {@inheritdoc}
     */
    public function generate(PageCollection $pageCollection, \Closure $messageCallback)
    {
        $generatedPages = new PageCollection();

        $filteredPages = $pageCollection->filter(function (Page $page) {
            return false !== $page->getTitle();
        });

        /* @var $page Page */
        foreach ($filteredPages as $page) {
            $alteredPage = clone $page;
            $alteredPage->setTitle(strtoupper($page->getTitle()));
            $generatedPages->add($alteredPage);
        }

        return $generatedPages;
    }
}

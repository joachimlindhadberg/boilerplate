<?php

declare(strict_types=1);

/** Optional public extension: published, canonical local URLs for sitemap.xml. */
interface ModuleSitemapProviderInterface
{
    /** @return array<int,array{path:string,lastmod?:string|null}> */
    public function sitemapEntries(): array;
}

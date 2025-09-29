<?php

namespace Webook\LaravelCMS\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * CMS Facade
 *
 * @method static string renderEditableContent(string $id, string $content = '')
 * @method static string closeEditableContent()
 * @method static string renderToolbar(array $options = [])
 * @method static string renderAssets(array $options = [])
 * @method static string renderConfig(array $options = [])
 */
class CMS extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'cms';
    }
}
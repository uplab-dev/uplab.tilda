<?php

namespace Uplab\Tilda;

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

class Common
{
    public static $publickey;
    public static $secretkey;
    public static $module_id = 'uplab.tilda';
    public static $inc = false;

    static function getOptions()
    {
        if (self::$inc) {
            return;
        }
        self::$inc = true;
        self::$publickey = \COption::GetOptionString(self::$module_id, "UPT_PUBLIC_KEY");
        self::$secretkey = \COption::GetOptionString(self::$module_id, "UPT_SECRET_KEY");
    }

    public static function getProjects($unicode = true)
    {
        static $projectsList = false;

        if ($projectsList !== false) {
            return $projectsList;
        }

        self::getOptions();

        $projectsList = Request::getData('getprojectslist');

        if ($projectsList) {
            foreach ($projectsList as &$value) {
                $value['title'] = Helper::convert2Win1251($value['title'], !$unicode);
                $value['descr'] = Helper::convert2Win1251($value['descr'], !$unicode);
                $value['title'] = str_replace(['"', "'"], '', $value['title']);
            }
        }

        return $projectsList;
    }

    public static function getPages($projectId = false, $unicode = true)
    {
        if (empty($projectId)) {
            return array();
        }

        $pagesList = Request::getData('getpageslist', ['projectid' => $projectId]);

        if (!empty($pagesList)) {
            foreach ($pagesList as &$value) {
                $value['title'] = Helper::convert2Win1251($value['title'], !$unicode);
                $value['descr'] = Helper::convert2Win1251($value['descr'], !$unicode);
                $value['title'] = str_replace(['"', "'"], '', $value['title']);
            }
        }

        return $pagesList;
    }

    public static function getAssocPagesList($projectId = false, $unicode = true)
    {
        if (empty($projectId)) {
            return [
                0 => Loc::getMessage('uplab.tilda_CHECK_PAGE'),
            ];
        }

        $arPages = array();
        $pagesList = self::getPages($projectId, $unicode);

        if (!empty($pagesList)) {
            foreach ($pagesList as $page) {
                $arPages[$page['id']] = $page['title'];
            }
        }

        return $arPages;
    }

    public static function getAssocProjectsList()
    {
        $arProjects = array();
        $projectsList = self::getProjects();

        if (!empty($projectsList)) {
            foreach ($projectsList as $project) {
                $arProjects[$project['id']] = $project['title'];
            }
        }

        return $arProjects;
    }

    static function getPageContent($page, $params = array())
    {
        $data = Request::getData('getpagefull', ['pageid' => $page]);

        $content = '';

        // Assets
        if (!empty($data['html'])) {
            $headMatches = [];
            preg_match('/<head\b[^>]*>(.*?)<\/head>/is', $data['html'], $headMatches);

            if (!empty($headMatches)) {
                $headContent = $headMatches[1];

                $assets = [];

                // Styles
                $styleMatches = [];
                preg_match_all('/<link\b[^>]*\brel=["\']stylesheet["\'][^>]*>/is', $headContent, $styleMatches, PREG_SET_ORDER);

                foreach ($styleMatches as $match) {
                    $assets[] = $match[0];
                }

                // Scripts
                $scriptMatches = [];
                preg_match_all('/<script\b[^>]*>(.*?)<\/script>/is', $headContent, $scriptMatches, PREG_SET_ORDER);

                foreach ($scriptMatches as $match) {
                    $assets[] = $match[0];
                }

                if (!empty($assets)) {
                    $content = implode('', $assets);
                }
            }
        }

        // HTML
        if (!empty($data['html'])) {
            $matches = array();
            preg_match_all('/<body\b[^>]*>(.*?)<\/body>/is', $data['html'], $matches, PREG_SET_ORDER);
            $html = Helper::convert2Win1251($matches[0][1]);

            if (!empty($html)) {
                $content .= $html;
            }
        }

        // Include page content
        if (!empty($content)) {
            $module_version = "";
            if ($info = \CModule::CreateModuleObject(self::$module_id)) {
                $module_version = $info->MODULE_VERSION;
            }

            $content = "<!--<TILDA ver=\"{$module_version}\">-->" . $content . "<!--</TILDA>-->";

            Helper::checkEventBeforeContentReplace($content);
        }

        return $content;
    }

    static function getPageFullContent($page)
    {
        $data = Request::getData('getpagefull', ['pageid' => $page]);

        $content = '';

        if (!empty($data['html'])) {
            $content = Helper::convert2Win1251($data['html']);

            Helper::checkEventBeforeContentReplace($content);
        }

        return $content;
    }

    static function getPageParts($page)
    {
        $data = Request::getData('getpagefull', ['pageid' => $page]);

        $module_version = "";
        if ($info = \CModule::CreateModuleObject(self::$module_id)) {
            $module_version = $info->MODULE_VERSION;
        }

        // Getting assets from <head>
        $assetsString = '';

        if (!empty($data['html'])) {
            $assets = [];

            // Styles (link tag)
            $styleMatches = [];
            preg_match_all('/<link\b[^>]*\brel=["\']stylesheet["\'][^>]*>/is', $data['html'], $styleMatches, PREG_SET_ORDER);

            foreach ($styleMatches as $match) {
                $assets[] = $match[0];
            }

            // Styles (style tag)
            $styleTagMatches = [];
            preg_match_all('/<style\b[^>]*>(.*?)<\/style>/is', $data['html'], $styleTagMatches, PREG_SET_ORDER);

            foreach ($styleTagMatches as $match) {
                $assets[] = $match[0];
            }

            // Scripts
            $scriptMatches = [];
            preg_match_all('/<script\b[^>]*>(.*?)<\/script>/is', $data['html'], $scriptMatches, PREG_SET_ORDER);

            foreach ($scriptMatches as $match) {
                $assets[] = $match[0];
            }

            if (!empty($assets)) {
                $assetsString = implode(PHP_EOL, $assets);

                $assetsString = "<!--<TILDA ver=\"{$module_version}\">-->{$assetsString}<!--</TILDA>-->";
            }
        }

        // Getting HTML from body
        $html = '';

        if (!empty($data['html'])) {
            $matches = array();
            preg_match_all('/<body\b[^>]*>(.*?)<\/body>/is', $data['html'], $matches, PREG_SET_ORDER);

            $html = Helper::convert2Win1251($matches[0][1]);

            // Removing assets from HTML
            // Styles
            $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);

            // Scripts
            $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);

            $html = "<!--<TILDA ver=\"{$module_version}\">-->{$html}<!--</TILDA>-->";
        }

        Helper::checkEventBeforeContentReplace($html);

        return [
            'assets' => $assetsString,
            'html'    => $html,
        ];
    }
}

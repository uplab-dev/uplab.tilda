<?php

namespace Uplab\Tilda;

use Bitrix\Main\Application;

class Replace
{
    static function tagReplace(&$content)
    {
        $request = Application::getInstance()->getContext()->getRequest();

        if ($request->isAdminSection()) {
            return false;
        }

        // Replace all occurrences of tag [UPLABTILDA ... ]
        if (preg_match_all("/\[(UPLABTILDA [^\]]+)\]/", $content, $matches)) {
            if (is_array($matches[1])) {
                foreach ($matches[1] as $k => $match) {
                    $content = self::replaceContent($content, $match, $matches[0][$k]);
                }
            } else {
                $content = self::replaceContent($content, $matches[1], $matches[0]);
            }

            // Replace JQuery .load method with .on
            $content = str_replace("$(window).load(function()", "$(window).on('load', function ()", $content);

            // Remove content marked with tag <!--UTilda--><!--EndUTilda-->
            Helper::removeCommentedCode($content);
        }

        return true;
    }

    static function replaceContent($content, $match, $tildaTag)
    {
        // Parsing tag service parameters: PROJECT, PAGE, HIDEPAGETEMPLATE, MOVERESOURCESTO
        $params = array();

        $explodedParams = preg_split("~(\s|&nbsp;)+~u", $match);
        foreach ($explodedParams as $value) {
            $keyValue = explode('=', $value);
            $keyValue[0] = str_replace("amp;", "", $keyValue[0]);
            $params[$keyValue[0]] = $keyValue[1];
        }

        if (
            !empty($params['HIDEPAGETEMPLATE']) &&
            $params['HIDEPAGETEMPLATE'] === "Y"
        ) {
            // Case: Don't display site template
            $content = Common::getPageFullContent(intval($params['PAGE']));
        } else {
            if (
                !empty($params['MOVERESOURCESTO'])
            ) {
                // Case: Display site template case + replace Tilda tag + move Tilda assets to head end
                $parts = Common::getPageParts(intval($params['PAGE']));

                switch ($params['MOVERESOURCESTO']) {
                    case 'HEADEND':
                        // Insert Tilda assets to head end
                        $content = str_replace('</head>', $parts['assets'] . '</head>', $content);
                        break;
                    case 'BODYEND':
                        // Insert Tilda assets to body end
                        $content = str_replace('</body>', $parts['assets'] . '</body>', $content);
                        break;
                }

                // Replace Tilda tag with content
                $content = str_replace($tildaTag, $parts['html'], $content);
            } else {
                // Case: Display site template case + replace Tilda tag
                $html = Common::getPageContent(intval($params['PAGE']), $params);

                $content = str_replace($tildaTag, $html, $content);
            }
        }

        return $content;
    }

    static function removeFromIndex($arFields)
    {
        if ($arFields["MODULE_ID"] === "iblock") {
            $arFields["TITLE"] = preg_replace(
                "/\[(UPLABTILDA [^\]]+)\]/",
                "",
                $arFields["TITLE"]
            );

            $arFields["BODY"] = preg_replace(
                "/\[(UPLABTILDA [^\]]+)\]/",
                "",
                $arFields["BODY"]
            );
        }

        return $arFields;
    }
}

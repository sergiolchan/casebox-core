<?php
namespace Casebox\CoreBundle\Service\Plugins;

use Casebox\CoreBundle\Service\Util;

/**
 * Class AutoSetFields
 */
class AutoSetFields
{
    /**
     * @param object $o
     *
     * @return void
     */
    public function onBeforeNodeDbCreateOrUpdate($o)
    {
        if (!is_object($o)) {
            return;
        }

        $objData = $o->getData();
        $template = $o->getTemplate();

        $title = @$o->getFieldValue('_title', 0)['value'];
        if (empty($title)) {
            if (!empty($template)) {
                $templateData = $template->getData();
                if (!empty($templateData['title_template'])) {
                    $title = $this->getAutoTitle($o);
                }
            }
        }
        if (!empty($title)) {
            $objData['name'] = $title;
        }

        $date = @$o->getFieldValue('_date_start', 0)['value'];
        if (!empty($date)) {
            $objData['date'] = Util\dateISOToMysql($date);
        }

        if (!empty($template)) {
            $dateEndField = $template->getField('_date_end');
            if (!empty($dateEndField)) {
                $date = @$o->getFieldValue('_date_end', 0)['value'];
                if (!empty($date)) {
                    $objData['date_end'] = Util\dateISOToMysql($date);
                } else {
                    $objData['date_end'] = null;
                }
            }
        }

        $o->setData($objData);
    }

    /**
     * Generate title string using given object data and titleTemplate
     *
     * @param object $object
     *
     * @return string
     */
    protected function getAutoTitle($object)
    {
        $rez = '';

        if (!is_object($object)) {
            return $rez;
        }

        $template = $object->getTemplate();

        if (empty($template)) {
            return $rez;
        }

        $templateData = $template->getData();
        // used from php templates of title
        $fields = [];
        $rez = str_replace(
            '{template_title}',
            @$templateData['title'],
            $templateData['title_template']
        );

        if (strpos($rez, '{') !== false) {
            $ld = $object->getLinearData();
            // Replace field values
            foreach ($ld as $field) {
                $tf = $template->getField($field['name']);
                $v = $template->formatValueForDisplay($tf, @$field['value'], false);

                // Decode special chars because formatValueForDisplay encodes textual values
                // and we obtain double encoded values in solr
                $v = htmlspecialchars_decode($v);

                if (is_array($v)) {
                    $v = implode(',', $v);
                }
                $v = addcslashes($v, '\'');
                $rez = str_replace('{'.$field['name'].'}', $v, $rez);
                $fields[$field['name']] = $v;
            }

            // Replacing field titles into object title variable
            foreach ($templateData['fields'] as $fv) {
                $rez = str_replace('{f'.$fv['name'].'t}', $fv['title'], $rez);

            }
        }

        // Evaluating the title if contains php code
        if (strpos($rez, '<?php') !== false) {
            // No more EVAL, use event handlers to automatially set Titles in complex situations
            // eval(' ?__>'.$rez.'<?php ');   also added '__' between ? and >
            if (!empty($title)) {
                $rez = $title;
            }
        }

        // Replacing any remained field placeholder from the title
        $rez = preg_replace('/\{[^\}]+\}/', '', $rez);
        $rez = stripslashes($rez);

        return $rez;
    }
}

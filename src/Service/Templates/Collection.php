<?php

namespace Casebox\CoreBundle\Service\Templates;

use Casebox\CoreBundle\Service\Cache;
use Casebox\CoreBundle\Service\DataModel as DM;
use Casebox\CoreBundle\Service\Objects\Template;

/**
 * Templates collection class
 */
class Collection
{
    /**
     * Array of Template classes
     * @var array
     */
    public $templates = [];

    /**
     * Flag to store if loadAll was allready called
     * @var bool
     */
    protected $loadedAll = false;

    /**
     * Load all templates from database
     *
     * @param boolean $reload reload even if already all loaded
     *
     * @return void
     */
    public function loadAll($reload = false)
    {
        // Skip loading if already loaded and reload not true
        if ($this->loadedAll && !$reload) {
            return;
        }

        $this->reset();
        // Collecting template_fields
        $fields = [];
        $headers = [];
        $templateId = false;
        $headerField = false;
        $prevLevel = 0;

        $recs = DM\TemplatesStructure::getFields();

        foreach ($recs as $r) {
            if (($templateId != $r['template_id']) || ($prevLevel != $r['level'])) {
                unset($headerField);
                $headerField = false;
                $prevLevel = $r['level'];
            }

            $templateId = $r['template_id'];
            unset($r['template_id']);

            if ($r['type'] == 'H') {
                unset($headerField);
                $headerField = &$r;
            }

            $headers[$templateId][$r['name']] = &$headerField;

            $fields[$templateId][$r['id']] = &$r;
            unset($r);
        }

        // Loading templates
        $recs = DM\Templates::readAllWithData();
        foreach ($recs as $r) {
            $r['fields'] = empty($fields[$r['id']]) ? [] : $fields[$r['id']];
            $r['headers'] = empty($headers[$r['id']]) ? [] : $headers[$r['id']];

            // Store template in collection
            $this->templates[$r['id']] = new Template($r['id'], false);
            $this->templates[$r['id']]->setData($r);
        }

        $this->loadedAll = true;
    }

    /**
     * Get template object by template id
     *
     * @return Template
     */
    public function getTemplate($templateId)
    {
        if (!empty($this->templates[$templateId])) {
            return $this->templates[$templateId];
        }
        $template = new Template($templateId, false);
        $template->load();

        $this->templates[$templateId] = $template;

        return $template;
    }

    /**
     * Get template object by its name
     *
     * @return Template
     */
    public function getTemplateByName($name)
    {
        foreach ($this->templates as $template) {
            $data = $template->getData();
            if ($data['name'] == $name) {
                return $template;
            }
        }

        $id = DM\Templates::toId($name);

        return $this->getTemplate($id);
    }

    /**
     * Get template type by its id
     *
     * @param int $id
     *
     * @return string
     */
    public function getType($id)
    {
        if (!is_numeric($id)) {
            return null;
        }

        // Check if template has been loaded
        if (!empty($this->templates[$id])) {
            return $this->templates[$id]->getData()['type'];
        }

        $var_name = 'template_type'.$id;

        if (!Cache::exist($var_name)) {
            $r = DM\Templates::read($id);

            if (!empty($r)) {
                Cache::set($var_name, $r['type']);
            }
        }

        return Cache::get($var_name);
    }

    /**
     * Get templates count from collection
     *
     * @return int
     */
    public function getCount()
    {
        return sizeof($this->templates);
    }

    /**
     * Reset this collection
     *
     * @return void
     */
    private function reset()
    {
        $this->templates = [];
        $this->loadedAll = false;
    }
}

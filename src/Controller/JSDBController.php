<?php
namespace Casebox\CoreBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;

use Casebox\CoreBundle\Service\Util;
use Casebox\CoreBundle\Service\Config;
use Casebox\CoreBundle\Service\Templates;

class JSDBController extends Controller
{
    /**
     * @Route(
     *     "/c/{coreName}/js/DB.js",
     *     name = "app_serve_js_db",
     *     requirements = {
     *         "coreName": "[a-z0-9_\-]+"
     *     }
     * )
     * @Method({"GET"})
     */
    public function indexAction($coreName, Request $request)
    {
        $configService = $this->get('casebox_core.service.config');
        $loginService = $this->get('casebox_core.service_auth.authentication');
        $ts = $this->get('translator');

        if (!$loginService->isLogged()) {
            throw new ResourceNotFoundException();
        }

        $data = [];

        $fieldTypes = [
            [null, '-'],
            ['_auto_title', $ts->trans('ftAutoTitle')], //Auto title (uses title_template)
            ['combo', $ts->trans('ftCombo')], //ComboBox
            ['date', $ts->trans('ftDate')], //Date
            ['datetime', $ts->trans('ftDatetime')], //Datetime
            ['float', $ts->trans('ftFloat')], //Float
            ['geoPoint', $ts->trans('ftGeoPoint')],
            ['G', $ts->trans('ftGroup')], //Group
            ['H', $ts->trans('ftHeader')], //Header
            ['html', $ts->trans('ftHtml')], //Html
            ['int', $ts->trans('ftInt')], //Integer
            ['memo', $ts->trans('ftMemo')], //Memo
            ['_objects', $ts->trans('ftObjects')], //Objects
            ['text', $ts->trans('ftText')], //Text
            ['time', $ts->trans('ftTime')], //Time
            ['varchar', $ts->trans('ftVarchar')], //Varchar
        ];

        $data['fieldTypes'] = empty($fieldTypes) ? '[]' : Util\jsonEncode($fieldTypes);

        $roles = [
            [1, $ts->trans('Administrator')],
            [2, $ts->trans('Manager')],
            [3, $ts->trans('Lawyer')],
            [4, $ts->trans('User')],
        ];

        $data['roles'] = Util\jsonEncode($roles);

        $ti = [];
        $templateIcons = $configService->get('templateIcons');

        if (!empty($templateIcons)) {
            $ti = explode(',', $templateIcons);
            $ti = implode("\n", $ti);
            $ti = str_replace("\r\n", "\n", $ti);
            $ti = explode("\n", $ti);

            for ($i = 0; $i < sizeof($ti); $i++) {
                $ti[$i] = [$ti[$i], $ti[$i]];
            }
        }

        $data['templatesIconSet'] = Util\jsonEncode($ti);

        /* languages */
        $coreLanguage = $configService->get('language');
        $coreLanguages = $configService->get('languagesUI');

        $ls = $configService->get('language_settings');

        $arr = [];
        for ($i = 0; $i < sizeof($coreLanguages); $i++) {
            $lang = empty($ls[$coreLanguages[$i]]) ? $ls[$coreLanguage] : $ls[$coreLanguages[$i]];

            $lp = [
                $i + 1,
                $coreLanguages[$i],
                $lang['name'],
                $lang['long_date_format'],
                $lang['short_date_format'],
                $lang['time_format'],
            ];

            for ($j = 0; $j < sizeof($lp); $j++) {
                $lp[$j] = str_replace(['%', '\/'], ['', '/'], $lp[$j]);
            }
            $arr[] = $lp;
        }

        $data['languages'] = empty($arr) ? '[]' : Util\jsonEncode($arr);
        /* end of languages */

        /* Security questions */
        $arr = [];
        for ($i = 0; $i < 10; $i++) {
            $sq = $ts->trans('SecurityQuestion'.$i);
            if (!empty($sq)) {
                $arr[] = [$i, $sq];
            }
        }
        $osq = $ts->trans('OwnSecurityQuestion');
        if (!empty($osq)) {
            $arr[] = [-1, $osq];
        }

        $data['securityQuestions'] = empty($arr) ? '[]' : Util\jsonEncode($arr);
        /* end of Security questions */

        /* templates */
        $templatesClass = new Templates();
        $tsd = $templatesClass->getTemplatesStructure();
        $templates = [];

        foreach ($tsd['data'] as $t => $fields) {
            $templates[$t] = [];
            foreach ($fields as $f) {
                $templates[$t][$f['pid']][] = $f;
            }
        }

        $data['templateStores'] = '';
        foreach ($templates as $t => $f) {
            $sf = [];
            $this->sortTemplateRows($f, null, $sf);
            $encode = Util\jsonEncode($sf);
            $data['templateStores'] .= 'CB.DB.template'.$t.' = new CB.DB.TemplateStore({data:'.$encode.'});'."\n\n";
        }

        $rez = $this->render('CaseboxCoreBundle::DB.js.twig', $data);

        $rez->setCharset('utf-8');

        return $rez;
    }

    /**
     * @param array $array
     * @param int   $pid
     * @param array $result
     */
    protected function sortTemplateRows(&$array, $pid, &$result)
    {
        if (empty($pid)) {
            $pid = null;
        }
        if (!empty($array[$pid])) {
            foreach ($array[$pid] as $r) {
                array_push($result, $r);
                $this->sortTemplateRows($array, $r['id'], $result);
            }
        }
    }
}

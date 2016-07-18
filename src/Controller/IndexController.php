<?php
namespace Casebox\CoreBundle\Controller;

use Casebox\CoreBundle\Service\Auth\CaseboxAuth;
use Casebox\CoreBundle\Service\Browser;
use Casebox\CoreBundle\Service\Cache;
use Casebox\CoreBundle\Service\Config;
use Casebox\CoreBundle\Service\Files;
use Casebox\CoreBundle\Service\User;
use Casebox\CoreBundle\Service\Objects;
use Casebox\CoreBundle\Service\Security;
use Casebox\CoreBundle\Traits\TranslatorTrait;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Casebox\CoreBundle\Service\Util;

/**
 * Class IndexController
 */
class IndexController extends Controller
{
    use TranslatorTrait;

    /**
     * @Route("/c/{coreName}/", name="app_core", requirements = {"coreName": "[a-z0-9_\-]+"})
     *      * @param Request $request
     * @param string $coreName
     *
     * @return Response
     * @throws \Exception
     */
    public function coreAction(Request $request, $coreName)
    {
        $configService = $this->get('casebox_core.service.config');

        /** @var CaseboxAuth $auth */
        $auth = $this->container->get('casebox_core.service_auth.authentication');

        $tsvAuth = $this->get('session')->get('auth');

        if (!$auth->isLogged(false) || !empty($tsvAuth)) {
            $auth->logout();

            return $this->redirectToRoute('app_core_login', ['coreName' => $coreName]);
        }

        $colors = User::getColors();
        foreach ($colors as $id => $c) {
            $colors[$id] = '.user-color-'.$id."{background-color: $c}";
        }

        $vars = [
            'projectName' => $configService->getProjectName(),
            'coreName' => $request->attributes->get('coreName'),
            'rtl' => $configService->get('rtl') ? '-rtl' : '',
            'cssUserColors' => '<style>'.implode("\n", $colors).'</style>',
            'styles' => $this->container->get('casebox_core.service.styles_service')->getRendered(),
            'locale' => $request->getLocale()
        ];

        $this->get('translator')->setLocale($vars['locale']);

        $vars['javascript'] = $this->container->get('casebox_core.service.javascript_service')->getRendered($vars);

        return $this->render('CaseboxCoreBundle::index.html.twig', $vars);
    }

    /**
     * @Route("/c/{coreName}/photo/.png", name="app_core_get_default_user_photo")
     * @Route(
     *     "/c/{coreName}/photo/{userId}.{extension}",
     *     defaults={"extension":"(jpg|png)"},
     *     name="app_core_get_user_photo"
     * )
     * @param Request $request
     * @param string  $coreName
     * @param int     $userId
     *
     * @return Response
     * @throws \Exception
     */
    public function getUserPhotoAction(Request $request, $coreName, $userId = null)
    {
        $auth = $this->container->get('casebox_core.service_auth.authentication');

        if (!$auth->isLogged(false)) {
            return $this->redirectToRoute('app_core_login', ['coreName' => $coreName]);
        }

        $photo = $this->container->getParameter('kernel.root_dir').'/../web/css/i/ico/32/user-male.png';

        if (!empty($userId)) {
            $q = (!empty($request->get('32'))) ? $request->get('32') : false;
            $photo = $this->container->get('casebox_core.service.user')->getPhotoFilename($userId, $q);
        }

        return new BinaryFileResponse($photo);
    }

    /**
     * @Route("/c/{coreName}/upload/", name="app_core_file_upload", requirements = {"coreName": "[a-z0-9_\-]+"})
     * @param Request $request
     * @param string  $coreName
     *
     * @return Response
     * @throws \Exception
     */
    public function uploadAction(Request $request, $coreName)
    {
        $configService = $this->get('casebox_core.service.config');
        $auth = $this->container->get('casebox_core.service_auth.authentication');

        if (!$auth->isLogged(false)) {
            return $this->redirectToRoute('app_core_login', ['coreName' => $coreName]);
        }

        $result = [
            'success' => false,
        ];

        if (isset($_SERVER['HTTP_X_FILE_OPTIONS'])) {
            $file = Util\jsonDecode($_SERVER['HTTP_X_FILE_OPTIONS']);
            $file['error'] = UPLOAD_ERR_OK;
            $file['tmp_name'] = tempnam($configService->get('incomming_files_dir'), 'cbup');
            $file['name'] = urldecode($file['name']);

            if (empty($file['content_id'])) {
                Util\bufferedSaveFile('php://input', $file['tmp_name']);
            }

            $_FILES = ['file' => $file];
            $browser = new Browser();

            $result = $browser->saveFile(
                [
                    'pid' => @$file['pid'],
                    'draftPid' => @$file['draftPid'],
                    'response' => @$file['response'],
                ]
            );
        }

        if (is_array($result)) {
            $result = json_encode($result);
        }

        return new Response($result, 200, ['Content-Type' => 'application/json', 'charset' => 'UTF-8']);
    }

    /**
     * @Route("/c/{coreName}/edit/{templateId}/{id}",
     *     name="app_core_item_edit",
     *     requirements = {"coreName": "[a-z0-9_\-]+"},
     *     defaults={"id" = null}
     * )
     * @param Request $request
     * @param string  $coreName
     * @param string  $id
     *
     * @return Response
     * @throws \Exception
     */
    public function editAction(Request $request, $coreName, $templateId, $id)
    {
        $configService = $this->get('casebox_core.service.config');
        $auth = $this->container->get('casebox_core.service_auth.authentication');

        if (!$auth->isLogged(false)) {
            return $this->redirectToRoute('app_core_login', ['coreName' => $coreName]);
        }

        $colors = User::getColors();
        foreach ($colors as $id => $c) {
            $colors[$id] = '.user-color-'.$id."{background-color: $c}";
        }

        $vars = [
            'projectName' => $configService->getProjectName(),
            'coreName' => $request->attributes->get('coreName'),
            'rtl' => $configService->get('rtl') ? '-rtl' : '',
            'cssUserColors' => '<style>'.implode("\n", $colors).'</style>',
            'styles' => $this->container->get('casebox_core.service.styles_service')->getRendered(),
            'locale' => $request->getLocale()
        ];

        $this->get('translator')->setLocale($vars['locale']);

        $vars['javascript'] = $this->container->get('casebox_core.service.javascript_service')->getRendered($vars);

        return $this->render('CaseboxCoreBundle::edit.html.twig', $vars);
    }

    /**
     * @Route("/c/{coreName}/view/{id}/", name="app_core_file_view", requirements = {"coreName": "[a-z0-9_\-]+"})
     * @param Request $request
     * @param string  $coreName
     * @param string  $id
     *
     * @return Response
     * @throws \Exception
     */
    public function viewAction(Request $request, $coreName, $id)
    {
        $configService = $this->get('casebox_core.service.config');
        $auth = $this->container->get('casebox_core.service_auth.authentication');

        if (!$auth->isLogged(false)) {
            return $this->redirectToRoute('app_core_login', ['coreName' => $coreName]);
        }

        list($id, $versionId) = explode('_', $id);

        if (!Security::canRead($id)) {
            return new Response($this->trans('Access_denied'));
        }

        $obj = Objects::getCachedObject($id);
        $objData = $obj->getData();
        $objType = $obj->getType();

        switch ($objType) {
            case 'file':
                if (empty($preview)) {
                    $preview = Files::generatePreview($id, $versionId); //$request->get('v')
                }

                $result = '';

                if (is_array($preview)) {
                    if (!empty($preview['processing'])) {
                        $result .= '&#160';
                    } else {
                        $top = '';
                        if (!empty($top)) {
                            $result .= $top.'<hr />';
                        }

                        $filesPreviewDir = $configService->get('files_preview_dir');

                        if (!empty($preview['filename'])) {
                            $fn = $filesPreviewDir.$preview['filename'];
                            if (file_exists($fn)) {
                                $result .= file_get_contents($fn);

                                $dbs = Cache::get('casebox_dbs');
                                $dbs->query('UPDATE file_previews SET ladate = CURRENT_TIMESTAMP WHERE id = $1', $id);
                            }
                        } elseif (!empty($preview['html'])) {
                            $result .= $preview['html'];
                        }
                    }
                }
                break;

            default:
                $preview = array();
                $o = new Objects();
                $pd = $o->getPluginsData(array('id' => $id));
                $title = '';

                if (!empty($pd['data']['objectProperties'])) {
                    $data = $pd['data']['objectProperties']['data'];
                    $title = '<div class="obj-header"><b class="">' . $data['name'] . '</div>';
                    $preview = $data['preview'];
                }

                $result = $title . implode("\n", $preview);
                break;
        }

        return new Response($result);
    }

    /**
     * @Route("/c/{coreName}/download/{id}/", name="app_core_file_download", requirements = {"coreName": "[a-z0-9_\-]+"})
     * @param Request $request
     * @param string  $coreName
     * @param string  $id
     *
     * @return Response
     * @throws \Exception
     */
    public function downloadAction(Request $request, $coreName, $id)
    {
        $result = [
            'success' => false,
        ];

        $headers = ['Content-Type' => 'application/json', 'charset' => 'UTF-8'];

        if (empty($id) || !is_numeric($id)) {
            $result['message'] = $this->trans(('Object_not_found'));

            return new Response(json_encode($result), 200, $headers);
        }

        $versionId = null;
        if (!empty($request->get('v'))) {
            $versionId = $request->get('v');
        }

        // check if public user is given
        $u = $request->get('u');
        if (isset($u) && is_numeric($u)) {
            $userId = $u;
            if (!User::isPublic($userId)) {
                exit(0);
            }
        } else {
            $auth = $this->container->get('casebox_core.service_auth.authentication');
            $user = $auth->isLogged(false);

            if (!$user) {
                return $this->redirectToRoute('app_core_login', ['coreName' => $coreName]);
            }

            $userId = $user->getId();
        }

        $pw = $request->get('pw');

        Files::download($id, $versionId, !isset($pw), $userId);

        return new Response(null, 200, $headers);
    }

    /**
     * @Route("/dav/{coreName}/{action}/{filename}", name="app_core_file_webdav")
     * @Route("/dav/{coreName}/{action}", name="app_core_file_webdav_action")
     * @Route("/dav/{coreName}", name="app_core_file_webdav_core")
     * @Route("/dav/", name="app_core_file_webdav_root")
     * @param Request $request
     * @param string  $coreName
     * @param string  $action
     * @param string  $filename
     *
     * @return Response
     * @throws \Exception
     */
    public function webdavAction(Request $request, $coreName, $action, $filename)
    {
        $result = [
            'success' => false,
        ];

        //$ary = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
        // error_log("initEnv: " . $_SERVER['REQUEST_URI']);
        // echo(print_r($ary, true));

        // remove URIPREFIX
        //array_shift($ary);

        $r = [];

        $r['core'] = $coreName;

        // current version
        // /dav/{core}/edit-{nodeId}/{filename}
        //
        // version history
        // /dav/{core}/edit-{nodeId}-{versionId}/{filename}
        // /dav/{core}/edit-{nodeId}-{versionId}/
        // also support a direct folder request /edit-{nodeId}
        if (preg_match('/^edit-(\d+)/', $action, $m)) {
            $r['mode'] = 'edit';

            $r['nodeId'] = $m[1];

            // /{core}/edit-{nodeId}-{versionId}/
            $r['editFolder'] = $action;

            // /edit-{nodeId}-{versionId}  ?
            if (preg_match('/^edit-(\d+)-(\d+)\//', $action, $m)) {
                $r['versionId'] = $m[2];
            }

            // {core}/edit-{nodeId}-{versionId}/{filename}
            // only if filename is specified
            if (!empty($filename)) {
                $r['filename'] = $filename;
            }

            // root Sabredav folder, serve all requests from here: /dav/{core}/edit-{nnn}
            // i.e. dav client should not try to get out of this folder
            $r['rootFolder'] = '/' . $r['editFolder'];
        } else {
            // $r['core'] = $m[1];
            $r['mode'] = 'browse';
            $r['rootFolder'] = '';
        }

        //$_GET['core'] = $r['core'];

        $webdav = $this->get('casebox_core.service.web_dav_service')->serve($r);
        if (!empty($webdav)) {
            $result = $webdav;
        }

        return new Response($result, 200, []);
    }

    /**
     * @Route("/", name="app_default")
     */
    public function indexAction()
    {
        $vars = [
            'locale' => $this->container->getParameter('locale')
        ];

        return $this->render('CaseboxCoreBundle::no-core-found.html.twig', $vars);
    }
}

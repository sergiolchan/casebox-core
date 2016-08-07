<?php
namespace Casebox\CoreBundle\Service;

use Casebox\CoreBundle\Service\Cache;
use Casebox\CoreBundle\Service\Util;
use Casebox\CoreBundle\Service\DataModel as DM;
use Casebox\CoreBundle\Service\PreviewExtractor;
use Casebox\CoreBundle\Traits\TranslatorTrait;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Process\Process;

/**
 * Class Files
 */
class Files
{
    use TranslatorTrait;

    /**
     * @param integer $id
     *
     * @return array
     * @throws \Exception
     */
    public static function getProperties($id)
    {
        $rez = [
            'success' => true,
            'data' => [],
        ];

        if (!is_numeric($id)) {
            return $rez;
        }

        $rez['menu'] = Browser\CreateMenu::getMenuForPath($id);

        $file = new Objects\File($id);
        $rez['data'] = $file->load();

        $d = &$rez['data'];
        $d['path'] = str_replace(',', '/', $d['path']);

        $t = strtotime($d['cdate']);
        $dateFormat = Util\getOption('long_date_format');
        $timeFormat = Util\getOption('time_format');

        $d['ago_date'] = date($dateFormat, $t).' '.self::trans('at').' '.date($timeFormat, $t);
        $d['ago_date'] = Util\translateMonths($d['ago_date']);
        $d['ago_text'] = Util\formatAgoTime($d['cdate']);

        if (!empty($d['versions'])) {
            foreach ($d['versions'] as &$r) {
                $r['template_id'] = $rez['data']['template_id'];
                $t = strtotime($r['cdate']);
                $r['ago_date'] = date($dateFormat, $t).' '.self::trans('at').' '.date($timeFormat, $t);
                $r['ago_date'] = Util\translateMonths($r['ago_date']);
                $r['ago_text'] = Util\formatAgoTime($r['cdate']);
            }
        }

        return $rez;
    }

    /**
     * @param array $p
     *
     * @return array
     * @throws \Exception
     */
    public function saveProperties($p)
    {
        // SECURITY: check if current user has write access
        if (!Security::canWrite($p['id'])) {
            throw new \Exception($this->trans('Access_denied'));
        }
        $file = new Objects\File($p['id']);
        $file->setData($p);
        $file->save();

        return ['success' => true];
    }

    /**
     * @param integer $id
     *
     * @return array
     * @throws \Exception
     */
    public static function getContent($id)
    {
        $rez = ['success' => true, 'data' => null];

        $file = new Objects\File($id);

        $data = $file->load();

        $configService = Cache::get('symfony.container')->get('casebox_core.service.config');

        $contentFile = $configService->get('files_dir').@$data['content_path'].'/'.@$data['content_id'];

        if (file_exists($contentFile) && !is_dir($contentFile)) {
            $rez['data'] = Util\toUTF8String(file_get_contents($contentFile));
        } else {
            Cache::get('symfony.container')->get('logger')->error(
                'Error accessing file ('.$id.'). Its content (id: '.@$data['content_id'].') doesnt exist on the disk.'
            );

            return ['success' => false];
        }

        return $rez;
    }

    /**
     * @param array $p
     *
     * @return array
     * @throws \Exception
     */
    public function saveContent($p)
    {
        if (!Security::canWrite($p['id'])) {
            throw new \Exception($this->trans('Access_denied'));
        }

        $this->saveCurrentVersion($p['id']);

        $file = new Objects\File($p['id']);
        $data = $file->load();

        $configService = Cache::get('symfony.container')->get('casebox_core.service.config');

        $content = [
            'tmp_name' => tempnam($configService->get('incomming_files_dir'), 'cbup'),
            'date' => date('Y-m-d'),
            'name' => $data['name'],
            'type' => @$data['type'],
        ];

        file_put_contents($content['tmp_name'], $p['data']);
        $content['size'] = filesize($content['tmp_name']);

        $this->storeContent($content);

        $data['content_id'] = $content['content_id'];
        $file->update($data);

        // $contentFile = $configService->get('files_dir') . $data['content_path'] . '/'.$data['content_id'];
        // file_put_contents($contentFile, $p['data']);
        return ['success' => true];
    }

    /**
     * @param integer           $id
     * @param integer|bool|null $versionId
     * @param bool              $asAttachment
     * @param integer|bool      $forUseId
     *
     * @throws \Exception
     */
    public static function download($id, $versionId = null, $asAttachment = true, $forUseId = false)
    {
        if (empty($versionId)) {
            $r = DM\Files::read($id);
        } else {
            $r = DM\FilesVersions::read($versionId);
        }

        if (!empty($r)) {
            $content = DM\FilesContent::read($r['content_id']);

            // Check if can download file
            if (!Security::canDownload($r['id'], $forUseId)) {
                throw new \Exception(self::trans('Access_denied'));
            }

            $configService = Cache::get('symfony.container')->get('casebox_core.service.config');
            $fileName = $configService->get('files_dir') . $content['path'] . DIRECTORY_SEPARATOR . $content['id'];

            if (file_exists($fileName)) {
                //replace any other db content type for pdfs with application/pdf (sometimes it is binary/octet-stream)
                if (strtolower(substr($r['name'], -4)) == '.pdf') {
                    $r['type'] = 'application/pdf';
                }

                header('Content-Description: File Transfer');
                header('Content-Type: '.$content['type'].'; charset=UTF-8');
                if ($asAttachment || ($content['type'] !== 'application/pdf')) {
                    // Purify filename for cases when we have a wrong filename in the system already
                    header('Content-Disposition: attachment; filename="'.Purify::filename($r['name']).'"');
                }

                header('Content-Transfer-Encoding: binary');
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                header('Content-Length: '.$content['size']);
                readfile($fileName);
            } else {
                header('HTTP/1.0 404 Not Found');
                header("Location: /img/fileNotFound.png");
            }
        } else {
            throw new \Exception(self::trans('Object_not_found'));
        }
    }

    /**
     * Save current file version into versions table
     * and delete versions exceeding mfvc
     *
     * @param int            $id   file Id
     * @param int|bool|false $mfvc Max file version count
     *
     * @return bool
     */
    protected function saveCurrentVersion($id, $mfvc = false)
    {
        if ($mfvc === false) {
            $mfvc = $this->getMFVC(Objects::getName($id));
        }

        if (empty($mfvc)) {
            return false;
        }

        $data = DM\Files::read($id);

        $data['file_id'] = $data['id'];
        unset($data['id']);

        DM\FilesVersions::create($data);

        // Detect versions exceeding mfvc and delete them
        if ($dIds = DM\FilesVersions::getOldestIds($id, $mfvc)) {
            DM\FilesVersions::delete($dIds);
        }

        return true;
    }

    /**
     * @param array $file
     */
    public static function extractUploadedArchive(&$file)
    {
        $archive = $file['name'];
        $ext = Files::getExtension($archive);
        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        $configService = Cache::get('symfony.container')->get('casebox_core.service.config');

        $incommingFilesDir = $configService->get('incomming_files_dir');

        switch ($ext) {
            case 'rar':
                $archive = rar_open($file['tmp_name']);
                if ($archive === false) {
                    return;
                }

                $file = [];
                $entries = rar_list($archive);
                foreach ($entries as $entry) {
                    if (!$entry->isDirectory()) { //we'll exclude empty directories
                        $tmp_name = tempnam($incommingFilesDir, 'cb_arch');
                        $entry->extract($incommingFilesDir, $tmp_name);
                        $file[] = [
                            'dir' => dirname($entry->getName()),
                            'name' => basename($entry->getName()),
                            'type' => finfo_file($finfo, $tmp_name),
                            'tmp_name' => $tmp_name,
                            'error' => 0,
                            'size' => $entry->getUnpackedSize(),
                        ];
                    }
                }
                rar_close($archive);

                break;

            case 'zip':
                $zip = zip_open($file['tmp_name']);

                if (!is_resource($zip)) {
                    return;
                }
                $file = [];
                while ($zip_entry = zip_read($zip)) {
                    $name = zip_entry_name($zip_entry);
                    if (substr($name, -1) == '/') {
                        continue; //exclude directories
                    }
                    $tmp_name = tempnam($incommingFilesDir, 'cb_arch');
                    $size = zip_entry_filesize($zip_entry);
                    if (zip_entry_open($zip, $zip_entry, "r")) {
                        file_put_contents($tmp_name, zip_entry_read($zip_entry, $size));
                        zip_entry_close($zip_entry);
                    }
                    $file[] = [
                        'dir' => dirname($name),
                        'name' => basename($name),
                        'type' => finfo_file($finfo, $tmp_name),
                        'tmp_name' => $tmp_name,
                        'error' => 0,
                        'size' => $size,
                    ];
                }
                zip_close($zip);

                break;
        }
    }

    /**
     * @param array $F
     *
     * @return bool
     */
    public function moveUploadedFilesToIncomming(&$F)
    {
        $configService = Cache::get('symfony.container')->get('casebox_core.service.config');

        $incommingFilesDir = $configService->get('incomming_files_dir');

        @mkdir($incommingFilesDir, 0777, true);

        foreach ($F as $fk => $f) {
            if (!empty($f['content_id'])) {
                // File content was not uploaded. Its content_id were sent as header param
                continue;
            }
            $new_name = $incommingFilesDir . basename($f['name']);

            if ($f['tmp_name'] == $new_name) {
                continue;
            }
            if (false === rename($f['tmp_name'], $new_name)) {
                return false;
            }
            $F[$fk]['tmp_name'] = $new_name;
        }

        return true;
    }

    /**
     * @param array $F
     *
     * @return bool
     */
    public function removeIncomingFiles($F)
    {
        foreach ($F as $f) {
            @unlink($f['tmp_name']);
        }

        return true;
    }

    /**
     * @param array    $F
     * @param ineteger $pid
     *
     * @return array
     */
    public function getExistentFilenames($F, $pid)
    {
        // If no file names already exists in target then the result will be an empty array
        $rez = [];

        foreach ($F as $f) {
            if ($this->fileExists($pid, $f['name'], @$f['dir'])) {
                $rez[] = $f;
            }
        }

        switch (sizeof($rez)) {
            case 0:
                break;

            case 1:
                // Single match: retreive match info for content
                // (if matches with current version or to an older version)
                $existentFileId = $this->getFileId($pid, $rez[0]['name'], @$rez[0]['dir']);
                $rez[0]['existentFileId'] = $existentFileId;
                $file = DM\Files::read($existentFileId);
                $content = DM\FilesContent::read($file['content_id']);

                $md5 = $this->getFileMD5($rez[0]);

                $data = [];

                if ($md5 == $content['md5']) {
                    $data = $file;
                    $data['text'] = $this->trans('FileContentsIdentical');
                }

                if (empty($rez[0]['msg'])) {
                    $version = DM\FilesVersions::getVersionByMD5($existentFileId, $md5);

                    if (!empty($version)) {
                        $data = $version;
                        $data['text'] = $this->trans('FileContentsIdenticalToAVersion');
                    }
                }

                if (!empty($data)) {
                    $user = User::getDisplayName($data['cid']);

                    $agoTime = Util\formatAgoTime($data['cdate']);

                    $rez[0]['msg'] = str_replace(
                        ['{timeAgo}', '{user}'],
                        [$agoTime, $user],
                        $data['text']
                    );
                }

                $subdirId = $pid;
                if (!empty($rez[0]['dir'])) {
                    $subdirId = $this->getFileId($pid, '', $rez[0]['dir']);
                }
                $rez[0]['suggestedFilename'] = Objects::getAvailableName($subdirId, $rez[0]['name']);

                break;

            default:
                break;
        }

        return $rez;
    }

    /**
     * @param array $p
     *
     * @return array
     */
    public function checkExistentContents($p)
    {
        $configService = Cache::get('symfony.container')->get('casebox_core.service.config');

        $filesDir = $configService->get('files_dir');

        foreach ($p as $k => $v) {
            $id = DM\FilesContent::toId($v, 'md5');

            if (!empty($id)) {
                $r = DM\FilesContent::read($id);
                // Give affirmative result only if the corresponding file content exists
                if (file_exists($filesDir . $r['path'] . DIRECTORY_SEPARATOR . $r['id'])) {
                    $p[$k] = $r['id'];
                } else {
                    $p[$k] = null;
                }
            } else {
                unset($p[$k]);
            }
        }

        return ['success' => true, 'data' => $p];
    }

    /**
     * @param array $FilesArray
     * @param array $result
     */
    public function attachPostUploadInfo(&$FilesArray, &$result)
    {
        if (!is_array($FilesArray)) {
            return;
        }
        // if a single file is uploaded then check if it has duplicates
        // and inform user about available file duplicates
        $msg = '';
        $prompt_to_open_file = false;

        switch (sizeof($FilesArray)) {
            case 0:
                break;
            case 1:
                reset($FilesArray);
                $f = current($FilesArray);
                $d = DM\Files::getDuplicates($f['id']);
                $paths = [];
                if (sizeof($d) > 0) {
                    foreach ($d as $dup) {
                        $paths[] = $dup['pathtext'];
                    }
                    $paths = array_unique($paths);
                    // msg: there are duplicates
                    $msg = str_replace(
                        '{paths}',
                        "\n<br />".implode('<br />', $paths),
                        $this->trans('UploadedFileExistsInFolders')
                    );
                    $prompt_to_open_file = true;
                    $result['data']['id'] = $f['id'];
                }

                break;

            default:
                $filenames = [];
                foreach ($FilesArray as $f) {
                    $d = DM\Files::getDuplicates($f['id']);
                    if (sizeof($d) > 1) {
                        //msg: Following files have duplicates
                        $filenames[] = (empty($f['dir']) ? '' : $f['dir'].DIRECTORY_SEPARATOR).$f['name'];
                    }
                }
                if (!empty($filenames)) {
                    $msg = $this->trans('FollowingFilesHaveDuplicates')."\n<br />".implode('<br />', $filenames);
                }
                break;
        }
        if (!empty($msg)) {
            $result['msg'] = $msg;
        }
        if ($prompt_to_open_file) {
            $result['prompt_to_open'] = true;
        }
    }

    /**
     * Checks if pid id exists in our tree or if filename exists under the pid.
     *
     * @param int    $pid
     * @param string $name
     * @param string $dir  an optional relative path under pid
     *
     * @return array
     */
    public static function getFileId($pid, $name = '', $dir = '')
    {
        $rez = null;

        $r = DM\Tree::read($pid);

        if (empty($r['dstatus'])) {
            $rez = $r['id'];
        }

        if (!empty($rez)) {
            if (!empty($name)) {
                $dir .= DIRECTORY_SEPARATOR.$name;
            }

            if (!empty($dir) && ($dir != '.')) {
                $dir = str_replace('\\', '/', $dir);
                $dir = explode('/', $dir);

                foreach ($dir as $dirName) {
                    if (empty($dirName) || ($dirName == '.')) {
                        continue;
                    }

                    $r = DM\Tree::getChildByName($rez, $dirName);

                    if (!empty($r)) {
                        $rez = $r['id'];
                    } else {
                        $rez = null;
                    }

                    if (empty($rez)) {
                        return $rez;
                    }
                }
            } else {
                $rez = null;
            }
        }

        return $rez;
    }

    /**
     * Checks if pid id exists in our tree or if filename exists under the pid.
     *
     * @param integer $pid
     * @param string  $name
     * @param string  $dir
     *
     * @return bool
     */
    public static function fileExists($pid, $name = '', $dir = '')
    {
        $file_id = static::getFileId($pid, $name, $dir);

        return !empty($file_id);
    }

    /**
     * Get file size
     *
     * @param int $id
     *
     * @return int
     */
    public static function getSize($id)
    {
        $rez = 0;

        $f = DM\Files::read($id);

        if (!empty($f)) {
            $c = DM\FilesContent::read($f['content_id']);
            if (!empty($c['size'])) {
                $rez = intval($c['size']);
            }
        }

        return $rez;
    }

    /**
     * @param array $p
     */
    public function saveUploadParams($p)
    {
        $configService = Cache::get('symfony.container')->get('casebox_core.service.config');

        $user = Cache::get('symfony.container')->get('session')->get('user');

        file_put_contents($configService->get('incomming_files_dir').$user['id'], serialize($p));
    }

    /**
     * @return bool|array|string
     */
    public function getUploadParams()
    {
        $rez = false;

        $configService = Cache::get('symfony.container')->get('casebox_core.service.config');

        $incomingFilesDir = $configService->get('incomming_files_dir');

        $user = Cache::get('symfony.container')->get('session')->get('user');
        if (file_exists($incomingFilesDir.$user['id'])) {
            $rez = file_get_contents($incomingFilesDir.$user['id']);
            $rez = unserialize($rez);
        }

        return $rez;
    }

    /**
     * storeFiles move the files from incomming folder to file storage
     *
     * @param array $p [ //upload params
     *       files property - array of uploaded files,
     *       response - response from user when asked about overwrite for single or many file
     * ]
     *
     * @return array|bool
     */
    public function storeFiles(&$p)
    {
        $configService = Cache::get('symfony.container')->get('casebox_core.service.config');

        // Here we'll iterate all files and comparing the md5 with already contained,
        // files will upload only new contents to our store. Existent contents will be reused
        foreach ($p['files'] as &$f) {
            if ($f['error'] == UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($f['error'] !== UPLOAD_ERR_OK) {
                continue;
            }

            // Apply general properties from $p to $f (file) variable
            foreach ($p as $k => $v) {
                $values = ['id', 'pid', 'draftPid', 'name', 'title', 'content_id', 'template_id', 'cid', 'oid', 'data'];
                if (in_array($k, $values)) {
                    $f[$k] = $v;
                }
            }

            @$f['date'] = Util\dateISOToMysql($p['date']);

            if (empty($f['template_id'])) {
                $f['template_id'] = $configService->get('default_file_template');
            }

            $this->storeContent($f);

            $pid = $p['pid'];
            if (!empty($f['dir'])) {
                $pid = $this->mkTreeDir($pid, $f['dir']);
                $f['pid'] = $pid;
            }

            $fileId = empty($p['id']) ? $this->getFileId($pid, $f['name']) : intval($p['id']);

            if (!empty($fileId)) {
                // Newversion, replace, rename, autorename, cancel
                switch (@$p['response']) {
                    case 'newversion':
                        // case 'overwrite':
                        // case 'overwriteall':
                        $this->saveCurrentVersion($fileId);

                        break;

                    case 'replace':
                        DM\Tree::delete($fileId, true);

                        $solr = new Solr\Client();
                        $solr->deleteByQuery('id:'.$fileId.' OR pids: '.$fileId);

                        break;

                    case 'rename':
                        $fileId = null;
                        $f['name'] = $p['newName']; // Here is the new name

                        break;

                    case 'autorename':
                        $fileId = null;
                        $f['name'] = Objects::getAvailableName($pid, $f['name']);

                        break;
                }
            }
            $f['type'] = 5;

            // Save file
            $fileObject = new Objects\File();
            if (!empty($fileId)) {
                $f['id'] = $fileId;
                if (@$p['response'] == 'replace') {
                    $fileObject->create($f);
                } else {
                    $fileObject->update($f);
                }
            } else {
                $f['id'] = $fileObject->create($f);
            }

            $this->updateFileProperties($f);
        }

        return true;
    }

    /**
     * @param array $p
     *
     * @return array
     */
    public function updateFileProperties($p)
    {
        if (empty($p['id'])) {
            return ['success' => false, 'msg' => $this->trans('Wrong_input_data')];
        }

        if (!Security::canWrite($p['id'])) {
            return ['success' => false, 'msg' => $this->trans('Access_denied')];
        }

        $p['title'] = strip_tags(@$p['title']);
        DM\Files::update(
            [
                'id' => $p['id'],
                'date' => Util\dateISOToMysql($p['date']),
                'title' => @$p['title'],
                'uid' => User::getId(),
                'udate' => 'CURRENT_TIMESTAMP',
            ]
        );

        Objects::updateCaseUpdateInfo($p['id']);

        return ['success' => true];
    }

    /**
     * @param integer $pid
     * @param string  $dir
     *
     * @return int
     */
    public function mkTreeDir($pid, $dir)
    {
        if (empty($dir) || ($dir == '.')) {
            return $pid;
        }

        $configService = Cache::get('symfony.container')->get('casebox_core.service.config');
        $path = str_replace('\\', '/', $dir);
        $path = explode('/', $path);
        $userId = User::getId();

        foreach ($path as $dir) {
            if (empty($dir)) {
                continue;
            }

            $r = DM\Tree::getChildByName($pid, $dir);

            if (!empty($r)) {
                $pid = $r['id'];
            } else {
                $pid = DM\Tree::create(
                    [
                        'pid' => $pid,
                        'name' => $dir,
                        'type' => 1,
                        'cid' => $userId,
                        'uid' => $userId,
                        'template_id' => $configService->get('default_folder_template'),
                    ]
                );
            }
        }

        return $pid;
    }

    /**
     * @param array $file
     *
     * @return null|string
     */
    private function getFileMD5(&$file)
    {
        if (empty($file)) {
            return null;
        }

        return md5_file($file['tmp_name']).'s'.$file['size'];
    }

    /**
     * @param array $f
     * @param bool  $filePath
     *
     * @return bool
     * @throws \Exception
     */
    public function storeContent(&$f, $filePath = false)
    {
        $configService = Cache::get('symfony.container')->get('casebox_core.service.config');

        if ($filePath == false) {
            $filePath = $configService->get('files_dir');
        }
        if (!empty($f['content_id']) && is_numeric($f['content_id'])) {
            return true; // content_id already defined
        }

        $f['content_id'] = null;
        if (!file_exists($f['tmp_name']) || ($f['size'] == 0)) {
            return false;
        }
        $md5 = $this->getFileMD5($f);

        $replaceNotExistingContentId = false;
        $contentId = DM\FilesContent::toId($md5, 'md5');
        if (!empty($contentId)) {
            $content = DM\FilesContent::read($contentId);
            if (file_exists($filePath.$content['path'].'/'.$content['id'])) {
                $f['content_id'] = $content['id'];
            } else {
                $replaceNotExistingContentId = $contentId;
            }
        }

        if (!empty($f['content_id'])) {
            unlink($f['tmp_name']);

            return true;
        }

        /* file date will be used from file variable (date parametter) if specified.
        If not specified then system file_date will be used */
        $date = false;
        if (!empty($f['date'])) {
            $date = strtotime($f['date']);
        }

        $storage_subpath = ($date === false)
            ? date('Y/m/d', filemtime($f['tmp_name']))
            : date('Y/m/d', $date);

        $data = [
            'size' => $f['size']
            ,'type' => $f['type']
            ,'path' => $storage_subpath
            ,'md5' => $md5
        ];

        if (!$replaceNotExistingContentId) {
            $f['content_id'] = DM\FilesContent::create($data);
        } else {
            $data['id'] = $replaceNotExistingContentId;
            DM\FilesContent::update($data);
            $f['content_id'] = $data['id'];
        }

        @mkdir($filePath.$storage_subpath.'/', 0777, true);

        if (copy($f['tmp_name'], $filePath.$storage_subpath.'/'.$f['content_id']) !== true) {
            throw new \Exception("Error copying file to destination folder, possible permission problems.", 1);
        }

        @unlink($f['tmp_name']);

        return true;
    }

    /**
     * @param integer $id
     */
    public function removeContentId($id)
    {
        $id = $id; //dummy codacy assignment
    }

    /**
     * @param array $file
     */
    public static function minimizeUploadedFile(&$file)
    {
        switch ($file['type']) {
            case 'application/pdf':
                // @todo - Refactoring needed
                $cmd = 'gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/screen -dNOPAUSE -dQUIET -dBATCH -sOutputFile='.$file['tmp_name'].'_min '.$file['tmp_name'];
                shell_exec($cmd);

                if (file_exists($file['tmp_name'].'_min')) {
                    $file['tmp_name'] .= '_min';
                    $file['size'] = filesize($file['tmp_name']);
                }

                break;
        }
    }

    /**
     * @param $id
     * @param bool $versionId
     *
     * @return array
     */
    public static function generatePreview($id, $versionId = false)
    {
        if (!is_numeric($id)) {
            if (strstr($id, '_')) {
                $array = explode('_', $id);
                $id = $array[0];
            }
        }

        $rez = [];
        $configService = Cache::get('symfony.container')->get('casebox_core.service.config');

        $coreName = $configService->get('core_name');
        $coreUrl = $configService->get('core_url');

        $filesDir = $configService->get('files_dir');
        $filesPreviewDir = $configService->get('files_preview_dir');

        @mkdir($filesPreviewDir, 0777, true);

        if (!empty($versionId)) {
            $file = DM\FilesVersions::read($versionId);
            $file['version_id'] = $versionId;
            $file['id'] = $id;
        } else {
            $file = DM\Files::read($id);
        }

        if (empty($file)) {
            Cache::get('symfony.container')->get('logger')
                ->error('Error accessing file preview ('.$id.'). Record not found.');

            return ['html' => ''];
        }

        $content = DM\FilesContent::read($file['content_id']);
        $preview = DM\FilePreviews::read($content['id']);

        if (!empty($preview)) {
            switch ($preview['status']) {
                case 1:
                case 2:
                    return ['processing' => true];

                case 3:
                    return ['html' => self::trans('ErrorCreatingPreview')];
            }
        }

        $ext = explode('.', $file['name']);
        $ext = array_pop($ext);
        $ext = strtolower($ext);
        $rez['ext'] = $ext;

        $rez['filename'] = $content['id'].'_.html';

        $previewFilename = $filesPreviewDir.$rez['filename'];

        $fn = $filesDir.$content['path'].DIRECTORY_SEPARATOR.$content['id'];
        // $nfn = $filesPreviewDir . $content['id'] . '_.' . $ext;

        if (!file_exists($fn)) {
            $message = 'Error accessing file preview ('.$id.'). Content (id: '.@$content['id'].') not found.';
            Cache::get('symfony.container')->get('logger')->error($message);

            return false;
        }

        switch ($ext) {
            case 'rtf':
            case 'doc':
            case 'xls':
            case 'csv':
            case 'ppt':
            case 'pps':
            case 'docx':
            case 'docm':
            case 'xlsx':
            case 'pptx':
            case 'odt':
                if (empty($preview)) {
                    DM\FilePreviews::create(
                        [
                            'id' => $content['id'],
                            'group' => 'office',
                            'status' => 1,
                            'filename' => null,
                            'size' => 0,
                            'cdate' => 'CURRENT_TIMESTAMP',
                        ]
                    );
                }

                if (file_exists($previewFilename)) {
                    $html = file_get_contents($previewFilename);
                    if (!empty($html)) {
                        return ['html' => $html];
                    }
                }

                /** @var Container $container */
                $container = Cache::get('symfony.container');
                $rootDir = $container->getParameter('kernel.root_dir');

                $config = Cache::get('platformConfig');

                $cmd = $rootDir.'/../bin/console'.' '.'casebox:preview:extract --env='.$config['coreName'];
                $process = new Process($cmd);
                $process->run();

                return ['processing' => true];
                break;

            case 'xml':
            case 'htm':
            case 'html':
            case 'dhtml':
            case 'xhtml':
                $content = file_get_contents($fn);
                $pe = new PreviewExtractor();
                $content = $pe->purify(
                    $content,
                    [
                        'URI.Base' => '/c/'.$coreName.'/',
                        'URI.MakeAbsolute' => true,
                    ]
                );
                file_put_contents($previewFilename, $content);
                break;

            case 'txt':
            case 'log':
            case 'css':
            case 'js':
            case 'json':
            case 'php':
            case 'bat':
            case 'ini':
            case 'sys':
            case 'sql':
                file_put_contents($previewFilename, '<pre>'.Util\adjustTextForDisplay(file_get_contents($fn)).'<pre>');
                break;

            case 'pdf':
                $html = 'PDF'; //Ext panel - PreviewPanel view
                if (empty($_SERVER['HTTP_X_REQUESTED_WITH'])) { //full browser window view
                    $url = $coreUrl.'download/'.$file['id'].'/';
                    $html = '
                        <object data="'.$url.'" type="application/pdf" width="100%" height="100%">
                            It appears you don\'t have Adobe Reader or PDF support in this web browser.
                            <a href="'.$url.'">Click here to download the file</a>
                            <embed src="'.$url.'" type="application/pdf" />
                        </object>';
                }

                return ['html' => $html];
                break;

            case 'tif':
            case 'tiff':
            case 'svg':
                $pfn = $filesPreviewDir.$content['id'];
                $convertedImages = [
                    $pfn.'_.png',
                ];

                if (!file_exists($convertedImages[0])) {
                    $convertedImages = [];
                    try {
                        $images = new \Imagick($fn);
                        $i = 1;
                        foreach ($images as $image) {
                            $image->setImageFormat('png');
                            $image->writeImage($pfn.$i.'_.png');
                            $convertedImages[] = $content['id'].$i.'_.png';
                            $i++;
                        }
                    } catch (\Exception $e) {
                        return $rez;
                    }
                }

                $ids = implode(
                    '" class="fit-img" style="margin: auto" />'."<br /><hr />\n".'<img src="/'.$coreName.'/view/',
                    $convertedImages
                );

                file_put_contents(
                    $previewFilename,
                    '<img src="/'.$coreName.'/view/'.$ids.'" class="fit-img" style="margin: auto" />'
                );
                break;

            default:
                if ((substr($content['type'], 0, 5) == 'image') && (substr($content['type'], 0, 9) !== 'image/svg')) {
                    file_put_contents(
                        $previewFilename,
                        '<div style="padding: 5px 10px"><img src="/c/'.$coreName.'/download/'.
                        $file['id'].
                        (empty($version_id) ? '' : '/'.$version_id).
                        '/" class="fit-img" style="margin: auto"></div>'
                    );
                }
        }

        if (!empty($preview)) {
            DM\FilePreviews::update(
                [
                    'id' => $file['content_id'],
                    'filename' => $rez['filename'],
                ]
            );
        } else {
            DM\FilePreviews::create(
                [
                    'id' => $file['content_id'],
                    'filename' => $rez['filename'],
                ]
            );
        }

        return $rez;
    }

    /**
     * @param integer $id
     */
    public static function deletePreview($id)
    {
        $configService = Cache::get('symfony.container')->get('casebox_core.service.config');

        $filesPreviewDir = $configService->get('files_preview_dir');

        if ($configService->get('is_windows', false)) {
            $cmd = 'del '.$filesPreviewDir.$id.'_*';
        } else {
            $cmd = 'find '.$filesPreviewDir.' -type f -name '.$id.'_* -print | xargs rm';
        }

        exec($cmd);
    }

    /**
     * @param ineteger $id
     *
     * @return array
     */
    public function restoreVersion($id)
    {
        $rez = [
            'success' => true,
            'data' => [
                'id' => 0,
                'pid' => 0,
            ],
        ];

        $fileId = 0;

        // Detect file id
        $version = DM\FilesVersions::read($id);

        if (!empty($version)) {
            $fileId = $version['file_id'];
            $rez['data']['id'] = $fileId;
        }

        // Get its pid
        $r = DM\Tree::read($fileId);
        if (!empty($r['pid'])) {
            $rez['data']['pid'] = $r['pid'];
        }

        $this->saveCurrentVersion($fileId);

        DM\Files::delete($fileId);
        DM\Files::create(
            [
                'id' => $fileId,
                'content_id' => $version['content_id'],
                'date' => $version['date'],
                'name' => $version['name'],
                'cid' => $version['cid'],
                'uid' => User::getId(),
                'cdate' => $version['cdate'],
                'udate' => $version['udate'],
            ]
        );

        Objects::updateCaseUpdateInfo($id);

        // Solr tree Update
        /** @var EventDispatcher $dispatcher */
        $dispatcher = Cache::get('symfony.container')->get('event_dispatcher');
        $dispatcher->dispatch('onSolrTreeUpdate');

        return $rez;
    }

    /**
     * @param integer $id
     *
     * @return array
     */
    public function deleteVersion($id)
    {
        $rez = ['success' => true, 'id' => $id];
        $content_id = 0;

        $version = DM\FilesVersions::read($id);

        if (!empty($version)) {
            $content_id = $version['content_id'];
        }

        DM\FilesVersions::delete($id);

        $this->removeContentId($content_id);

        DM\Tree::update(
            [
                'id' => $version['file_id'],
                'updated' => 1,
            ]
        );

        Objects::updateCaseUpdateInfo($id);

        // Solr tree Update
        /** @var EventDispatcher $dispatcher */
        $dispatcher = Cache::get('symfony.container')->get('event_dispatcher');
        $dispatcher->dispatch('onSolrTreeUpdate');

        return $rez;
    }

    /**
     * @param array $ids
     *
     * @return array
     */
    public function merge($ids)
    {
        if (!is_array($ids)) {
            return ['success' => false];
        }

        $ids = Util\toNumericArray($ids);

        if (sizeof($ids) < 2) {
            return ['success' => false];
        }

        $to_id = null;

        $dbs = Cache::get('casebox_dbs');

        $res = $dbs->query('SELECT id  FROM tree WHERE id IN ('.implode(', ', $ids).') ORDER BY udate DESC, id DESC');

        if ($r = $res->fetch()) {
            $to_id = $r['id'];
        }
        unset($res);

        $dbs->query('UPDATE files_versions SET file_id = $1 WHERE file_id IN ('.implode(', ', $ids).')', $to_id);

        $dbs->query(
            'INSERT INTO files_versions (file_id, content_id, `date`, name, cid, uid, cdate, udate)
                SELECT $1
                    ,content_id
                    ,`date`
                    ,name
                    ,cid
                    ,uid
                    ,cdate
                    ,udate
                FROM files
                WHERE id <> $1 AND id in('.implode(',', $ids).')',
            $to_id
        );

        $dbs->query(
            'UPDATE tree SET did = $2, dstatus = 1, updated = (updated | 1)
             WHERE id <> $1 AND id IN ('.implode(', ', $ids).')',
            [
                $to_id,
                User::getId(),
            ]
        );

        DM\Tree::update(
            [
                'id' => $to_id,
                'updated' => 1,
            ]
        );

        $ids = array_diff($ids, [$to_id]);

        // Objects::updateCaseUpdateInfo($id);

        // Solr tree Update
        /** @var EventDispatcher $dispatcher */
        $dispatcher = Cache::get('symfony.container')->get('event_dispatcher');
        $dispatcher->dispatch('onSolrTreeUpdate');

        return ['success' => true, 'rez' => $ids];
    }

    /**
     * @param string $filename
     *
     * @return int
     */
    public function getRAROriginalSize($filename)
    {
        $size = 0;
        $resource = rar_open($filename);
        if ($resource === false) {
            return $size;
        }

        $entries = rar_list($resource);
        foreach ($entries as $entry) {
            //we'll exclude empty directories
            if (!$entry->isDirectory()) {
                $size += $entry->getUnpackedSize();
            }
        }
        rar_close($resource);

        return $size;
    }

    /**
     * @param string $filename
     *
     * @return int
     */
    public function getZIPOriginalSize($filename)
    {
        $size = 0;
        $resource = zip_open($filename);
        if (!is_resource($resource)) {
            return $size;
        }

        while ($dir_resource = zip_read($resource)) {
            $size += zip_entry_filesize($dir_resource);
        }
        zip_close($resource);

        return $size;
    }

    /**
     * @param string $filename
     *
     * @return string
     */
    public static function getExtension($filename)
    {
        $ext = explode('.', $filename);
        if (sizeof($ext) < 2) {
            return '';
        }
        $ext = array_pop($ext);
        $ext = trim($ext);

        return mb_strtolower($ext);
    }

    /**
     * @param string $filename
     *
     * @return string
     */
    public static function getIcon($filename)
    {
        if (empty($filename)) {
            return 'file-unknown';
        }

        return 'file- file-'.Files::getExtension($filename);
    }

    /**
     * @param string $filename
     *
     * @return string
     */
    public static function getIconFileName($filename)
    {
        $ext = Files::getExtension($filename);
        switch ($ext) {
            case 'docx':
            case 'rtf':
                $ext = 'doc';
                break;
            case 'pptx':
                $ext = 'ppt';
                break;
            case 'txt':
                $ext = 'text';
                break;
            case 'html':
                $ext = 'htm';
                break;
            case 'rm':
                $ext = 'mp3';
                break;
            case 'gif':
            case 'jpg':
            case 'jpeg':
            case 'tif':
            case 'bmp':
            case 'png':
                $ext = 'img';
                break;
        }
        $filename = $ext.'.png';

        if (file_exists(Cache::get('platformConfig')['cb_doc_root'].'/css/i/ext/'.$filename)) {
            return $filename;
        } else {
            return '.png';
        }
    }

    /**
     * Storing max file versions count (mfvc)
     *     *:1;doc,docx,xls,xlsx,pdf:5;
     *     default is no versions if nothing specified in config
     *
     * @param string $configurationString
     *
     * @return array
     */
    public static function setMFVC($configurationString)
    {
        $rez = ['*' => 0];

        if (!empty($configurationString)) {
            $v = explode(';', $configurationString);
            foreach ($v as $vc) {
                $vc = explode(':', $vc);
                if (sizeof($vc) == 2) {
                    $ext = trim($vc[0]);
                    $count = trim($vc[1]);
                    if (is_numeric($count)) {
                        $ext = explode(',', $ext);
                        foreach ($ext as $e) {
                            $e = trim($e);
                            $e = mb_strtolower($e);
                            $rez[$e] = $count;
                        }
                    }
                }
            }
        }

        $configService = Cache::get('symfony.container')->get('casebox_core.service.config');
        $configService->setEnvVar('mfvc', $rez);

        return $rez;
    }

    /**
     * Get Max File Version Count for an extension
     *
     * @param string $filename
     *
     * @return int
     */
    public static function getMFVC($filename)
    {
        $ext = Files::getExtension($filename);
        if (empty($ext)) {
            $ext = mb_strtolower($filename);
        }

        $ext = trim($ext);

        $rez = 0;

        $configService = Cache::get('symfony.container')->get('casebox_core.service.config');

        $mfvc = $configService->get('mfvc');

        if (empty($mfvc)) {
            return $rez;
        }

        $ext = mb_strtolower($ext);

        if (isset($mfvc[$ext])) {
            return $mfvc[$ext];
        }
        if (isset($mfvc['*'])) {
            return $mfvc['*'];
        }

        return $rez;
    }

    /**
     * Upload a file to CaseBox using post method
     *
     * @param array $p {
     *    int           $pid        parent object Id
     *    varchar       $localFile  the absolute location of a file on the same server  /var/www/website.com/book.pdf
     *    int           $template_id | $tmplId  Template id of the file to be added.
     *                      In CaseBox each tree object has a template.
     *                      If this param is not specified then default_file_template is used (if defined in core
     *     config). array         $data | $tmplData    file metadata according to the template {'language': 'english',
     *     'price': '$10'} isodate       $date    the date in mysql format    2012-03-27T10:25 varchar|int   $oid |
     *     $owner   the username or id of the file owner varchar       $title | $filename    the title that will
     *     replace the original filename of the uploaded file  new-book.pdf varchar       $fileExistAction =
     *     (newversion|replace|autorename) Action to be taken when file exist in target.
     * }
     *    'file' name of the POST variable from Files when posting a file (multipart/form-data).
     *
     * @return array      responce
     * @throws \Exception
     */
    public function upload($p)
    {
        // Check params validity
        $params_validation = $this->validateInputParamsForUpload($p);
        if ($params_validation !== true) {
            throw new \Exception("Params validation failed: ".$params_validation, 1);
        }

        $configService = Cache::get('symfony.container')->get('casebox_core.service.config');

        if (empty($p['data']) && !empty($p['tmplData'])) {
            $p['data'] = $p['tmplData'];
            unset($p['tmplData']);
        }

        if (empty($p['response']) && $this->fileExists($p['pid'], $p['title'])) {
            throw new \Exception("File exists in target: ".$p['title'], 1);
        }

        if (!empty($p['localFile'])) {
            $file_name = basename($p['localFile']);
            $tmp_name = $configService->get('incomming_files_dir').$file_name;
            $finfo = finfo_open(FILEINFO_MIME_TYPE);

            copy($p['localFile'], $tmp_name);

            $p['files']['file'] = [
                'name' => empty($p['title']) ? $file_name : $p['title'],
                'tmp_name' => $tmp_name,
                'error' => 0,
                'size' => filesize($p['localFile']),
                'type' => finfo_file($finfo, $tmp_name),
            ];
        } else {
            $p['files'] = &$_FILES;
        }
        if (empty($p['files']) || ($p['files']['file']['error'] !== UPLOAD_ERR_OK)) {
            throw new \Exception('File upload error', 1);
        }
        $this->storeFiles($p);

        $rez = ['success' => true, 'data' => $p['files']];

        return $rez;
    }

    /**
     * @param array $p
     *
     * @return bool|string
     */
    private function validateInputParamsForUpload(&$p)
    {
        if (!isset($p['pid'])) {
            return 'pid not specified';
        }

        if (!is_numeric($p['pid'])) {
            return 'pid not valid';
        }

        $configService = Cache::get('symfony.container')->get('casebox_core.service.config');

        if (empty($p['template_id']) && !empty($p['tmplId'])) {
            $p['template_id'] = $p['tmplId'];
        }

        if (empty($p['template_id'])) {
            $p['template_id'] = $configService->get('default_file_template');

            if (empty($p['template_id'])) {
                return 'template not specified';
            }
        }

        if (!empty($p['fileExistAction'])) {
            if (!in_array($p['fileExistAction'], ['newversion', 'replace', 'autorename'])) {
                return 'Invalid value for fileExistAction';
            }
            $p['response'] = $p['fileExistAction'];
            unset($p['fileExistAction']);
        }

        if (!is_numeric($p['template_id'])) {
            return 'template id not valid';
        }

        if (!empty($p['localFile'])) {
            if (!file_exists($p['localFile'])) {
                return 'File not found: '.$p['localFile'];
            }
        } else {
            if (empty($_FILES)) {
                return 'No file found for upload';
            }
        }

        if (empty($p['title'])) {
            if (!empty($p['filename'])) {
                $p['title'] = $p['filename'];
                unset($p['filename']);
            } else {
                if (!empty($p['localFile'])) {
                    $p['title'] = basename($p['localFile']);
                } elseif (!empty($_FILES['file'])) {
                    $p['title'] = $_FILES['file']['name'];
                }
            }
        }
        if (empty($p['title'])) {
            return 'Cannot detect file title';
        }

        if (!isset($p['oid'])) {
            if (!isset($p['owner'])) {
                return 'owner not specified';
            }

            if (is_numeric($p['owner'])) {
                if (DM\Users::idExists($p['owner'])) {
                    $p['oid'] = $p['owner'];
                }
            } else {
                $p['oid'] = DM\Users::getIdByName($p['owner']);
            }
        }

        if (!is_numeric($p['oid'])) {
            return 'invalid owner specified';
        } elseif (empty($p['cid'])) {
            $p['cid'] = $p['oid'];
        }

        return true;
    }
}

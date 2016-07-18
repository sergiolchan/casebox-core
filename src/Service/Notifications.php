<?php
namespace Casebox\CoreBundle\Service;

use Casebox\CoreBundle\Service\DataModel as DM;
use Casebox\CoreBundle\Service\Objects\Object;
use Casebox\CoreBundle\Traits\TranslatorTrait;

/**
 * Class Notifications
 */
class Notifications
{
    use TranslatorTrait;

    private static $template = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
        "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
        <html xmlns="http://www.w3.org/1999/xhtml" lang="{lang}" xml:lang="{lang}">
        <head><title>CaseBox</title><meta http-equiv="Content-Type" content="text/html; charset=UTF-8" /></head>
        <body>{body}</body></html>';

    private static $updateFieldColors = array(
        'added' => 'A8D08D'
        ,'updated' => '#FFC000'
        ,'removed' => 'C00000'
    );

    /**
     * remotely accessible method to get the notifications list
     * @param  arra $p remote params (page, limit, etc)
     * @return json response
     */
    public function getList($p)
    {
        $this->prepareParams($p);

        $params = array(
            'user_id' => User::getId()
            ,'limit' => $p['limit']
        );

        return array(
            'success' => true
            ,'lastSeenActionId' => User::getUserConfigParam('lastSeenActionId', 0)
            ,'data' => $this->getRecords($params)
        );
    }

    /**
     * get new notification records
     * @param  array $p containing fromId property
     * @return json  response
     */
    public function getNew($p)
    {
        if (User::isLogged()) {
            $rez = array(
                'success' => true
                , 'data' => array()
            );

            $this->prepareParams($p);

            $p['user_id'] = User::getId();

            $fromId = empty($p['fromId'])
                ? false
                : intval($p['fromId']);

            $rez['data'] = $this->getRecords($p);
            $rez['lastSeenActionId'] = User::getUserConfigParam('lastSeenActionId', 0);

            User::setUserConfigParam('lastNotifyTime', Util\dateISOToMysql('now'));

        } else {
            $rez = array(
                'success' => false
            );
        }

        return $rez;
    }

    /**
     * update last seen laction id
     * @return json response
     * @param  int  $actionId
     */
    public static function updateLastSeenActionId($actionId, $userId = false)
    {
        $rez = array('success' => false);

        if ($userId == false) {
            $userId = User::getId();
        }

        if (is_numeric($actionId) and ($actionId > 0)) {
            User::setUserConfigParam('lastSeenActionId', $actionId, $userId);
            DM\Notifications::markAsSeenUpToActionId($actionId, $userId);
            $rez = array('success' => true);
        }

        return $rez;
    }

    /**
     * mark a notification record as read
     * @param  array $p containing "id" (returned client side id) and "ids"
     * @return json  response
     */
    public function markAsRead($p)
    {
        $rez = array('success' => false);

        if (!empty($p['ids'])) {
            DM\Notifications::markAsRead($p['ids'], User::getId());

            $rez = array(
                'success' => true
                ,'data' => $p
            );
        }

        return $rez;
    }

    /**
     * mark a notification record as unread
     * @param  array $p containing "id" (returned client side id) and "ids"
     * @return json  response
     */
    public function markAsUnread($p)
    {
        $rez = array('success' => false);

        if (!empty($p['ids'])) {
            DM\Notifications::markAsRead($p['ids'], User::getId(), 0);

            $rez = array(
                'success' => true
                ,'data' => $p
            );
        }

        return $rez;
    }

    /**
     * mark all unread user notifications  as read
     * @return json response
     */
    public function markAllAsRead()
    {
        DM\Notifications::markAllAsRead(User::getId());

        return array(
            'success' => true
        );
    }

    /**
     * get details for given notification ids
     * @param  array $p
     * @return json  response
     */
    public function getDetails($p)
    {
        $rez = array(
            'success' => true
            ,'ids' => $p['ids']
            ,'data' => ''
        );

        //collect action log ids
        $logIds = [];
        $recs = DM\Notifications::readByIds($p['ids']);

        foreach ($recs as $r) {
            $logIds = array_merge($logIds, Util\toNumericArray($r['action_ids']));
        }
        $logIds = array_unique($logIds);

        $recs = DM\Log::getRecords($logIds);

        foreach ($recs as $r) {
            $d = Util\jsonDecode($r['data']);

            $html = '<hr /><b class="user">' . User::getDisplayName($r['user_id']) .
                '</b>, <span class="gr" title="' .
                Util\formatMysqlTime($r['action_time']) .
                '">' . Util\formatAgoTime($r['action_time']) . '</span>';

            switch ($r['action_type']) {
                case 'comment':
                    $html .= '<br />' . nl2br(Comment::processAndFormatMessage($d['comment']));
                    break;

                default:
                    $obj = Objects::getCachedObject($r['object_id']);
                    $diff = $obj->getDiff($d);
                    if (!empty($diff)) {
                        $html .= "<table class=\"as-diff\">";
                        foreach ($diff as $fn => $fv) {
                            $html .= "<tr><th>$fn</th><td>$fv</td></tr>";
                        }
                        $html .= "</table>";
                    }
            }

            $rez['data'] = $html . $rez['data'];
        }

        return $rez;
    }

    /**
     * get action records and group them for notifications display
     * @param  string $sql
     * @param  array  $params sql params
     * @return array
     */
    private function getRecords($p)
    {
        $rez = array();

        $recs = DM\Notifications::getLast(
            $p['user_id'],
            $p['limit'],
            empty($p['fromId']) ? false : $p['fromId']
        );

        $actions = array();
        //grouping actions by object_id, action type and read property
        foreach ($recs as $r) {
            $r['data'] = Util\toJsonArray($r['data']);
            $group = $r['object_id'] . '_' . $r['action_type'] . '_' . $r['read'];
            $actions[$group][$r['from_user_id']] = $r;
        }

        //iterate actions and group into records up to read property
        foreach ($actions as $group => $users) {
            //form id
            $ids = array(); //would be comma separated action_ids
            foreach ($users as $r) {
                $ids[] = $r['id'];
            }
            $r = current($users);

            $forUserId = '';
            if (!empty($r['data']['forUserId']) &&
                    ($r['from_user_id'] != $r['data']['forUserId'])
            ) {
                $arr = array(
                    $r['data']['forUserId'] => 1
                );
                $forUserId = ' ' . $this->getUsersString($arr) . ' ' . $this->trans('in') . ' ';

            }

            $record = array(
                'ids' => implode(',', $ids)
                ,'read' => $r['read']
                ,'action_id' => $r['action_id']
                ,'user_id' => $r['from_user_id']
                ,'object_id' => $r['object_id']
                ,'text' => $this->getUsersString($users) . ' ' .
                        $this->getActionDeclination($r['action_type']) . $forUserId . ' ' .
                        $this->getObjectName($r['data'])  . //with icon
                        '<div class="cG" style="padding-top: 2px">' . Util\formatAgoTime($r['action_time']). '</div>'

            );

            if (in_array($r['action_type'], ['create', 'update', 'comment'])) {
                $record['expandable'] = true;
            }

            if (is_numeric($record['ids'])) {
                $record['id'] = $record['ids'];
            }

            $rez[] = $record;
        }

        return $rez;
    }

    /**
     * forms a user string based on their count
     * @param  array  &$usersArray grouped users array
     * @return string
     */
    private function getUsersString(&$usersArray)
    {
        $rez = '';

        $usersCount = sizeof($usersArray);
        $userIds = array_keys($usersArray);
        $users = array();

        foreach ($userIds as $id) {
            // onClick will show popup user profile
            $users[] = '<a class="user" href="#">' . User::getDisplayName($id) . '</a>';
        }

        switch ($usersCount) {
            case 1:
                $rez = $users[0];
                break;

            case 2:
                $rez = $users[0] . ' ' . $this->trans('and') . ' ' . $users[1];
                break;

            case 3:
                $rez = $users[0] . ', ' . $users[1] . ' ' . $this->trans('and') . ' ' . $users[2];
                break;

            default:
                $rez = $users[0] . ', ' . $users[1] . ' ' . $this->trans('and') . ' ' .
                    str_replace('{count}', $usersCount -2, $this->trans('NNOthers'));
        }

        return $rez;
    }

    /**
     * get action type declination
     * @param  string $actionType
     * @param  string $lang
     * @return string
     */
    public static function getActionDeclination($actionType, $lang = false)
    {
        $rez = '';

        switch ($actionType) {
            case 'create':
            case 'update':
            case 'delete':
            case 'complete':
            case 'close':
            case 'rename':
            case 'status_change':
            case 'comment_update':
            case 'move':
                $rez = self::trans($actionType . 'd', $lang);
                break;

            case 'reopen':
                $rez = self::trans($actionType . 'ed', $lang);
                break;

            case 'comment':
                $rez = self::trans($actionType . 'edOn', $lang);
                break;

            case 'file_upload':
                $rez = self::trans($actionType . 'ed_to', $lang);
                break;

            case 'file_update':
                $rez = self::trans($actionType . 'd_in', $lang);
                break;

            case 'completion_decline':
                $rez = self::trans('completionDeclinedFor', $lang);
                break;

            case 'completion_on_behalf':
                $rez = self::trans('completedOnBehalf', $lang);
                break;

            default:
                $rez = $actionType;
                //to review and discuss
                /*
                'overdue'
                'password_change'
                'permissions'
                'user_delete'
                'user_create'
                'login'
                'login_fail'/**/
        }

        return $rez;
    }

    /**
     * format an object name using its data
     * @param  array  $data
     * @return string
     */
    private function getObjectName($data)
    {
        return '<a class="click obj-ref" itemid="'. $data['id'] .
            '" templateid="'. $data['template_id'] .
            '" title="'. $data['name'] .
            '">'. $data['name'] . '</a>';
    }

    /**
     * prepare input params of a request
     * @param  array &$p
     * @return void
     */
    protected function prepareParams(&$p)
    {
        $limit = (empty($p['limit']) || !is_numeric($p['limit']))
            ? 200
            : intval($p['limit']);

        if ($limit > 500) {
            $limit = 500;
        }

        $p['limit'] = $limit;
    }

    //-------------------  rendering methods

    /**
     * analize given action (from action log)
     * and create corresponding mail body
     * @return string
     */
    public static function getMailBodyForAction(&$action, &$userData)
    {
        $configService = Cache::get('symfony.container')->get('casebox_core.service.config');

        $coreUrl = $configService->get('core_url');
        $name = $action['data']['name'];
        $languages = $configService->get('languages');
        $lang = $languages[$userData['language_id'] -1];

        //set header row by default
        $rez = '<h3><a href="' . $coreUrl . 'view/' . $action['object_id'] . '/">' . $name . '</a></h3>';

        switch ($action['action_type']) {
            case 'comment':
            case 'comment_update':
                $rez .= static::getCommentMailBody($action, $userData);
                break;

            case 'create':
            case 'delete':
            case 'move':
                $rez .= static::getObjectMailBody($action);
                break;

            default:
                $rez .= static::getActionDiffMailBody($action, static::$updateFieldColors);
        }

        $rez = str_replace(
            array(
                '{lang}',
                '{body}'
            ),
            array(
                $lang,
                $rez
            ),
            static::$template
        );

        return $rez;
    }

    /**
     * get mail body for a comment
     * @param  array  $action
     * @param  array  $colors
     * @return string
     */

    protected static function getCommentMailBody(&$action, &$userData)
    {
        $rez = nl2br(Object::processAndFormatMessage($action['data']['comment']));
        $rez .='<br /><hr />'.'To add a comment, reply to this email.<br />';
            // <a href="#">Unsubscribe</a> (will not receive emails with new comments for “' . $name . '”)';
        return $rez;
    }

    /**
     * get mail body for a generic object
     * @param  array  $action
     * @param  array  $colors
     * @return string
     */
    protected static function getObjectMailBody($action, $colors = false)
    {
        $rez = '';
        $rows = array();

        $obj = Objects::getCachedObject($action['object_id']);
        $tpl = $obj->getTemplate();
        $ld = $obj->getLinearData(true);

        $ad = &$action['data'];

        foreach ($ld as $fieldData) {
            $fieldName = $fieldData['name'];
            $field = $tpl->getField($fieldName);

            $type = 'none';
            if (!empty($ad['new'][$fieldName]) && empty($ad['old'][$fieldName])) {
                $type = 'added';
            } elseif (!empty($ad['old'][$fieldName]) && empty($ad['new'][$fieldName])) {
                $type = 'removed';
            }
            /* // we dont have updated cases here
            elseif (!empty($ad['old'][$fieldName]) && !empty($ad['new'][$fieldName]) &&
                ($ad['old'][$fieldName] != $ad['new'][$fieldName])
            ) {
                $type = 'updated';
            }/**/

            $color = empty($colors[$type])
                ? ''
                : (' style="background-color: ' . $colors[$type] . '"');

            $value = $tpl->formatValueForDisplay($field, $fieldData, true);
            if (!empty($value) || ($type == 'removed')) {
                $rows[] = '<tr><td style="vertical-align: top"><strong>' . $field['title'] . '</strong></td>' .
                    '<td' . $color . '>' . $value . '</td></tr>';
            }
        }

        if (!empty($rows)) {
            $rez = '<table border="1" style="border-collapse: collapse" cellpadding="3">'.
                '<th style="background-color: #d9d9d9">' . self::trans('Property') . '</th>' .
                '<th style="background-color: #d9d9d9">' . self::trans('Value') . '</th></tr>' .
                implode("\n", $rows) . '</table>';
        }

        return $rez;
    }

    /**
     * get mail body diff for a generic object
     * @param  array  $action
     * @param  array  $colors
     * @return string
     */
    protected static function getActionDiffMailBody($action, $colors = false)
    {
        $rez = '';
        $rows = array();

        $obj = Objects::getCachedObject($action['object_id']);
        $tpl = $obj->getTemplate();

        $ad = &$action['data'];

        $oldData = empty($ad['old'])
            ? array()
            : $ad['old'];

        $newData = empty($ad['new'])
            ? array()
            : $ad['new'];

        $keys = array_keys($oldData + $newData);

        foreach ($keys as $fieldName) {
            $field = $tpl->getField($fieldName);

            $oldValue = null;
            if (!empty($oldData[$fieldName])) {
                $oldValue = array();
                foreach ($oldData[$fieldName] as $v) {
                    $v = $tpl->formatValueForDisplay($field, $v, true, true);
                    if (!empty($v)) {
                        $oldValue[] = $v;
                    }
                }
                $oldValue = implode('<br />', $oldValue);
            }

            $newValue = null;
            if (!empty($newData[$fieldName])) {
                $newValue = array();
                foreach ($newData[$fieldName] as $v) {
                    $v = $tpl->formatValueForDisplay($field, $v, true, true);
                    if (!empty($v)) {
                        $newValue[] = $v;
                    }
                }
                $newValue = implode('<br />', $newValue);
            }

            $type = 'none';
            if (!empty($newValue) && empty($oldValue)) {
                $type = 'added';
            } elseif (!empty($oldValue) && empty($newValue)) {
                $type = 'removed';
            } elseif (!empty($oldValue) && !empty($newValue) && ($oldValue != $newValue)) {
                $type = 'updated';
            }

            $color = empty($colors[$type])
                ? ''
                : (' style="background-color: ' . $colors[$type] . '"');

            $value = empty($oldValue)
                ? ''
                : "<del>$oldValue</del><br />";
            $value .= $newValue;

            if (!empty($value)) {
                $rows[] = '<tr><td style="vertical-align: top"><strong>' . $field['title'] . '</strong></td>' .
                    '<td' . $color . '>' . $value . '</td></tr>';
            }
        }

        if (!empty($rows)) {
            $rez = '<table border="1" style="border-collapse: collapse" cellpadding="3">'.
                '<th style="background-color: #d9d9d9">' . self::trans('Property') . '</th>' .
                '<th style="background-color: #d9d9d9">' . self::trans('Value') . '</th></tr>' .
                implode("\n", $rows) . '</table>';
        }

        return $rez;
    }

    /**
     * get the sender formated string
     * @param  integer|false $userId
     * @return string
     */
    public static function getSender($userId = false)
    {
        $configService = Cache::get('symfony.container')->get('casebox_core.service.config');

        $coreName = $configService->get('core_name');

        $commentsEmail = $configService->get('comments_email');

        $senderMail = empty($commentsEmail)
            ? $configService->get('sender_email')
            : $commentsEmail;

        $rez = '"' .
            mb_encode_mimeheader(
                str_replace(
                    '"',
                    '\'\'',
                    html_entity_decode(
                        User::getDisplayName($userId) . " (" . $coreName . ")",
                        ENT_QUOTES,
                        'UTF-8'
                    )
                ),
                'UTF-8',
                'B'
            )
            ."\" <" . $senderMail . '>';

        return $rez;
    }
}

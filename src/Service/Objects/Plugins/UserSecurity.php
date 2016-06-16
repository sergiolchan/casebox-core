<?php
namespace Casebox\CoreBundle\Service\Objects\Plugins;

use Casebox\CoreBundle\Service\Cache;
use Casebox\CoreBundle\Service\Objects;
use Casebox\CoreBundle\Service\User;
use Casebox\CoreBundle\Service\DataModel as DM;

class UserSecurity extends Html
{

    public function getData($id = false)
    {
        $config = $this->config;

        $userId = User::getId();

        $rez = parent::getData($id);

        $container = Cache::get('symfony.container');
        $twig = $container->get('twig');

        $rec = DM\Users::read($this->id);
        $vars = [
            'activeUserId' => User::getId(),
            'user' => $rec,
        ];

        $rez['data'] = $twig->render('CaseboxCoreBundle:plugins:userSecurity.html.twig', $vars);

        return $rez;
    }
}

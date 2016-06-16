<?php

namespace Casebox\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\Exclude;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * UsersGroups
 *
 * @ORM\Table(
 *     name="users_groups",
 *     indexes={
 *          @ORM\Index(name="IDX_type", columns={"type"}),
 *          @ORM\Index(name="IDX_recover_hash", columns={"recover_hash"}),
 *          @ORM\Index(name="FK_users_groups_language", columns={"language_id"})
 *     },
 *     uniqueConstraints={@ORM\UniqueConstraint(name="IDX_type__name", columns={"type", "name"})}
 * )
 * @ORM\Entity(repositoryClass="Casebox\CoreBundle\Repository\UsersGroupsRepository")
 */
class UsersGroups implements UserInterface, \Serializable
{
    const ROLE_USER            = 'ROLE_USER';
    const ROLE_ADMIN           = 'ROLE_ADMIN';
    const TYPE_GROUP           = 1;
    const TYPE_USER            = 2;
    const SYSTEM_NONPERSISTENT = 0;
    const SYSTEM_PERSISTENT    = 1;

    /**
     * @ORM\Id
     * @ORM\Column(name="id", type="integer", options={"unsigned":true})
     * @ORM\GeneratedValue(strategy="AUTO")
     * @var int
     */
    protected $id;

    /**
     * @ORM\Column(name="name", type="string", length=100)
     * @var string
     */
    protected $name;

    /**
     * @ORM\Column(name="email", type="string", length=100)
     * @var string
     */
    protected $email;

    /**
     * @ORM\Column(name="password", type="string", nullable=true)
     * @var string
     * @Exclude()
     */
    protected $password;

    /**
     * @ORM\Column(name="password_change", type="date", nullable=true)
     * @var string
     * @Exclude()
     */
    protected $passwordChange;

    /**
     * @ORM\Column(name="salt", type="string")
     * @var string
     * @Exclude()
     */
    protected $salt;

    /**
     * @ORM\Column(name="roles", type="json_array")
     * @var array
     */
    protected $roles;

    /**
     * @ORM\Column(name="first_name", type="string", nullable=true, length=60)
     * @var string
     */
    protected $firstName;

    /**
     * @ORM\Column(name="last_name", type="string", nullable=true, length=60)
     * @var string
     */
    protected $lastName;

    /**
     * @ORM\Column(name="type", type="integer", length=3, options={"unsigned": true, "default": 2})
     * @var integer
     */
    protected $type;

    /**
     * @ORM\Column(name="system", type="integer", length=3, options={"unsigned": true, "default": 0})
     * @var integer
     */
    protected $system;

    /**
     * @ORM\Column(name="sex", type="string", nullable=true, length=1)
     * @var string
     */
    protected $sex;

    /**
     * @ORM\Column(name="photo", type="string", nullable=true, length=250)
     * @var string
     */
    protected $photo;

    /**
     * @ORM\Column(name="recover_hash", type="string", length=100, nullable=true)
     * @var string
     */
    protected $recoverHash;

    /**
     * @ORM\Column(name="language_id", type="integer", length=6, options={"unsigned": true, "default": 1})
     * @var integer
     */
    protected $languageId;

    /**
     * @ORM\Column(name="cfg", type="text")
     * @var string
     */
    protected $cfg;

    /**
     * @ORM\Column(name="data", type="text")
     * @var string
     */
    protected $data;

    /**
     * @ORM\Column(name="last_login", type="integer", nullable=true)
     * @var integer
     */
    protected $lastLogin;

    /**
     * @ORM\Column(name="login_successful", type="integer", length=1, nullable=true)
     * @var integer
     */
    protected $login_successful;

    /**
     * @ORM\Column(name="login_from_ip", type="string", length=40, nullable=true)
     * @var string
     */
    protected $loginFromIp;

    /**
     * @ORM\Column(name="last_logout", type="integer", nullable=true)
     * @var integer
     */
    protected $lastLogout;

    /**
     * @ORM\Column(name="last_action_time", type="integer", nullable=true)
     * @var integer
     */
    protected $lastActionTime;

    /**
     * @ORM\Column(name="enabled", type="integer", length=1, nullable=true, options={"default": 1})
     * @var integer
     */
    protected $enabled;

    /**
     * @ORM\Column(name="cid", type="integer", length=11, nullable=true, options={"unsigned": true})
     * @var integer
     */
    protected $cid;

    /**
     * @ORM\Column(name="cdate", type="integer", options={"default": 0})
     * @var integer
     */
    protected $cdate;

    /**
     * @ORM\Column(name="uid", type="integer", nullable=true, options={"unsigned": true})
     * @var integer
     */
    protected $uid;

    /**
     * @ORM\Column(name="udate", type="string", nullable=true, options={"default": "0000-00-00 00:00:00"})
     * @var string
     */
    protected $udate;

    /**
     * @ORM\Column(name="did", type="integer", nullable=true, options={"unsigned":true})
     * @var integer
     */
    protected $did;

    /**
     * @ORM\Column(name="ddate", type="integer", nullable=true)
     * @var integer
     */
    protected $ddate;

    /**
     * @ORM\Column(name="searchField", type="text", nullable=true)
     * @var int
     */
    protected $searchField;

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->email;
    }

    /**
     * User constructor
     */
    public function __construct()
    {
        $this->type = self::TYPE_USER;
        $this->system = self::SYSTEM_NONPERSISTENT;
        $this->languageId = 1;
        $this->enabled = 1;
        $this->cdate = time();
        $this->cdate = new \DateTime();
        $this->udate = '0000-00-00 00:00:00';
        $this->roles = [self::ROLE_USER => self::ROLE_USER];
    }

    /**
     * @return bool
     */
    public function isAccountNonExpired()
    {
        return true;
    }

    /**
     * @return bool
     */
    public function isAccountNonLocked()
    {
        return true;
    }

    /**
     * @return bool
     */
    public function isCredentialsNonExpired()
    {
        return true;
    }

    /**
     * @return null
     */
    public function eraseCredentials()
    {
        // core...
        return null;
    }

    /**
     * @return string
     * @see \Serializable::serialize()
     */
    public function serialize()
    {
        return serialize([$this->id, $this->name, $this->password, $this->salt]);
    }

    /**
     * @param string $serialized
     *
     * @return array
     * @see \Serializable::unserialize()
     */
    public function unserialize($serialized)
    {
        list ($this->id, $this->name, $this->password, $this->salt) = unserialize($serialized);
    }

    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getUsername()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     *
     * @return UsersGroups $this
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @param string $email
     *
     * @return UsersGroups $this
     */
    public function setEmail($email)
    {
        $this->email = $email;

        return $this;
    }

    /**
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @param string $password
     *
     * @return UsersGroups $this
     */
    public function setPassword($password)
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @return string
     */
    public function getPasswordChange()
    {
        return $this->passwordChange;
    }

    /**
     * @param string $passwordChange
     *
     * @return UsersGroups $this
     */
    public function setPasswordChange($passwordChange)
    {
        $this->passwordChange = $passwordChange;

        return $this;
    }

    /**
     * @return string
     */
    public function getSalt()
    {
        return $this->salt;
    }

    /**
     * @param string $salt
     *
     * @return UsersGroups $this
     */
    public function setSalt($salt)
    {
        $this->salt = $salt;

        return $this;
    }

    /**
     * @return array
     */
    public function getRoles()
    {
        return $this->roles;
    }

    /**
     * @param array $roles
     *
     * @return UsersGroups $this
     */
    public function setRoles($roles)
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @return string
     */
    public function getFirstName()
    {
        return $this->firstName;
    }

    /**
     * @param string $firstName
     *
     * @return UsersGroups $this
     */
    public function setFirstName($firstName)
    {
        $this->firstName = $firstName;

        return $this;
    }

    /**
     * @return string
     */
    public function getLastName()
    {
        return $this->lastName;
    }

    /**
     * @param string $lastName
     *
     * @return UsersGroups $this
     */
    public function setLastName($lastName)
    {
        $this->lastName = $lastName;

        return $this;
    }

    /**
     * @return int
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param int $type
     *
     * @return UsersGroups $this
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @return string
     */
    public function getSystem()
    {
        return $this->system;
    }

    /**
     * @param string $system
     *
     * @return UsersGroups $this
     */
    public function setSystem($system)
    {
        $this->system = $system;

        return $this;
    }

    /**
     * @return string
     */
    public function getL1()
    {
        return $this->l1;
    }

    /**
     * @param string $l1
     *
     * @return UsersGroups $this
     */
    public function setL1($l1)
    {
        $this->l1 = $l1;

        return $this;
    }

    /**
     * @return string
     */
    public function getL2()
    {
        return $this->l2;
    }

    /**
     * @param string $l2
     *
     * @return UsersGroups $this
     */
    public function setL2($l2)
    {
        $this->l2 = $l2;

        return $this;
    }

    /**
     * @return string
     */
    public function getL3()
    {
        return $this->l3;
    }

    /**
     * @param string $l3
     *
     * @return UsersGroups $this
     */
    public function setL3($l3)
    {
        $this->l3 = $l3;

        return $this;
    }

    /**
     * @return string
     */
    public function getL4()
    {
        return $this->l4;
    }

    /**
     * @param string $l4
     *
     * @return UsersGroups $this
     */
    public function setL4($l4)
    {
        $this->l4 = $l4;

        return $this;
    }

    /**
     * @return string
     */
    public function getSex()
    {
        return $this->sex;
    }

    /**
     * @param string $sex
     *
     * @return UsersGroups $this
     */
    public function setSex($sex)
    {
        $this->sex = $sex;

        return $this;
    }

    /**
     * @return string
     */
    public function getPhoto()
    {
        return $this->photo;
    }

    /**
     * @param string $photo
     *
     * @return UsersGroups $this
     */
    public function setPhoto($photo)
    {
        $this->photo = $photo;

        return $this;
    }

    /**
     * @return string
     */
    public function getRecoverHash()
    {
        return $this->recoverHash;
    }

    /**
     * @param string $recoverHash
     *
     * @return UsersGroups $this
     */
    public function setRecoverHash($recoverHash)
    {
        $this->recoverHash = $recoverHash;

        return $this;
    }

    /**
     * @return int
     */
    public function getLanguageId()
    {
        return $this->languageId;
    }

    /**
     * @param int $languageId
     *
     * @return UsersGroups $this
     */
    public function setLanguageId($languageId)
    {
        $this->languageId = $languageId;

        return $this;
    }

    /**
     * @return string
     */
    public function getCfg()
    {
        return $this->cfg;
    }

    /**
     * @param string $cfg
     *
     * @return UsersGroups $this
     */
    public function setCfg($cfg)
    {
        $this->cfg = $cfg;

        return $this;
    }

    /**
     * @return string
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param string $data
     *
     * @return UsersGroups $this
     */
    public function setData($data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * @return int
     */
    public function getLastLogin()
    {
        return $this->lastLogin;
    }

    /**
     * @param int $lastLogin
     *
     * @return UsersGroups $this
     */
    public function setLastLogin($lastLogin)
    {
        $this->lastLogin = $lastLogin;

        return $this;
    }

    /**
     * @return int
     */
    public function getLoginSuccessful()
    {
        return $this->login_successful;
    }

    /**
     * @param int $login_successful
     *
     * @return UsersGroups $this
     */
    public function setLoginSuccessful($login_successful)
    {
        $this->login_successful = $login_successful;

        return $this;
    }

    /**
     * @return string
     */
    public function getLoginFromIp()
    {
        return $this->loginFromIp;
    }

    /**
     * @param string $loginFromIp
     *
     * @return UsersGroups $this
     */
    public function setLoginFromIp($loginFromIp)
    {
        $this->loginFromIp = $loginFromIp;

        return $this;
    }

    /**
     * @return int
     */
    public function getLastLogout()
    {
        return $this->lastLogout;
    }

    /**
     * @param int $lastLogout
     *
     * @return UsersGroups $this
     */
    public function setLastLogout($lastLogout)
    {
        $this->lastLogout = $lastLogout;

        return $this;
    }

    /**
     * @return int
     */
    public function getLastActionTime()
    {
        return $this->lastActionTime;
    }

    /**
     * @param int $lastActionTime
     *
     * @return UsersGroups $this
     */
    public function setLastActionTime($lastActionTime)
    {
        $this->lastActionTime = $lastActionTime;

        return $this;
    }

    /**
     * @return int
     */
    public function getEnabled()
    {
        return $this->enabled;
    }

    /**
     * @param int $enabled
     *
     * @return UsersGroups $this
     */
    public function setEnabled($enabled)
    {
        $this->enabled = $enabled;

        return $this;
    }

    /**
     * @return int
     */
    public function getCid()
    {
        return $this->cid;
    }

    /**
     * @param int $cid
     *
     * @return UsersGroups $this
     */
    public function setCid($cid)
    {
        $this->cid = $cid;

        return $this;
    }

    /**
     * @return int
     */
    public function getCdate()
    {
        return $this->cdate;
    }

    /**
     * @param int $cdate
     *
     * @return UsersGroups $this
     */
    public function setCdate($cdate)
    {
        $this->cdate = $cdate;

        return $this;
    }

    /**
     * @return int
     */
    public function getUid()
    {
        return $this->uid;
    }

    /**
     * @param int $uid
     *
     * @return UsersGroups $this
     */
    public function setUid($uid)
    {
        $this->uid = $uid;

        return $this;
    }

    /**
     * @return string
     */
    public function getUdate()
    {
        return $this->udate;
    }

    /**
     * @param string $udate
     *
     * @return UsersGroups $this
     */
    public function setUdate($udate)
    {
        $this->udate = $udate;

        return $this;
    }

    /**
     * @return int
     */
    public function getDid()
    {
        return $this->did;
    }

    /**
     * @param int $did
     *
     * @return UsersGroups $this
     */
    public function setDid($did)
    {
        $this->did = $did;

        return $this;
    }

    /**
     * @return int
     */
    public function getDdate()
    {
        return $this->ddate;
    }

    /**
     * @param int $ddate
     *
     * @return UsersGroups $this
     */
    public function setDdate($ddate)
    {
        $this->ddate = $ddate;

        return $this;
    }

    /**
     * @return string
     */
    public function getSearchField()
    {
        return $this->searchField;
    }

    /**
     * @param string $searchField
     *
     * @return UsersGroups $this
     */
    public function setSearchField($searchField)
    {
        $this->searchField = $searchField;

        return $this;
    }
}

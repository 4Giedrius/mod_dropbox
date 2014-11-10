<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (http://www.boxbilling.com)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

namespace Box\Mod\Dropbox;
require_once BB_PATH_MODS . '/Dropbox/dropbox-sdk/autoload.php';


class Service implements \Box\InjectionAwareInterface
{
    protected $di;

    public function setDi($di)
    {
        $this->di = $di;
    }

    public function getDi()
    {
        return $this->di;
    }

    public function install()
    {
        $sql="
        CREATE TABLE IF NOT EXISTS `dropbox` (
        `id` bigint(20) NOT NULL AUTO_INCREMENT,
        `path` varchar(256) DEFAULT NULL,
        `name` varchar(256) DEFAULT NULL,
        `created_at` varchar(35) DEFAULT NULL,
        `updated_at` varchar(35) DEFAULT NULL,
        PRIMARY KEY (`id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
        ";
        $this->di['db']->exec($sql);
    }

    public function uninstall()
    {
        $this->di['db']->exec('DROP TABLE dropbox;');
    }

    public function getDropboxAppInfo()
    {
        return \Dropbox\AppInfo::loadFromJsonFile(BB_PATH_MODS . "/Dropbox/config.json");
    }

    public function getAuthLink()
    {
        $appInfo      = $this->getDropboxAppInfo();
        $webAuth      = new \Dropbox\WebAuthNoRedirect($appInfo, "PHP-Example/1.0");
        $authorizeUrl = $webAuth->start();

        return $authorizeUrl;
    }

    public function saveToken($authCode)
    {
        $appInfo = $this->getDropboxAppInfo();
        $webAuth = new \Dropbox\WebAuthNoRedirect($appInfo, "PHP-Example/1.0");
        list($accessToken, $dropboxUserId) = $webAuth->finish(trim($authCode));
        $api    = $this->di['api_admin'];
        $config = array(
            'ext'             => 'mod_dropbox',
            'auth_code'       => $authCode,
            'access_token'    => $accessToken,
            'dropbox_user_id' => $dropboxUserId
        );

        return $api->extension_config_save($config);
    }

    public function getDropboxClient()
    {
        $api    = $this->di['api_admin'];
        $config = $api->extension_config_get(array('ext' => 'mod_dropbox'));
        if (!isset($config['access_token']) || empty($config['access_token'])) {
            throw new \Box_Exception('Dropbox access token missing. Please configure it from admin area');
        }
        $accessToken = $config['access_token'];

        return new \Dropbox\Client($accessToken, "PHP-Example/1.0");;

    }

    public function uploadFile($file)
    {
        $f = fopen($file['tmp_name'], "rb");
        $dbxClient = $this->getDropboxClient();
        $result = $dbxClient->uploadFile('/' . $file['name'], \Dropbox\WriteMode::add(), $f);
        return true;
    }
    


}
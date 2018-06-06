<?php

require_once(TOOLKIT . '/class.event.php');
require_once(EXTENSIONS . '/members_login_oauth2/extension.driver.php');
require_once(EXTENSIONS . '/members_login_oauth2/vendor/autoload.php');

class eventmembers_oauth2_login extends Event
{
    public static function about()
    {
        return array(
            'name' => extension_members_login_oauth2::EXT_NAME,
            'author' => array(
                'name' => 'Symphony Community',
                'website' => 'https://www.getsymphony.com/',
                'email' => 'team@getsymphony.com',
            ),
            'version' => '1.0.0',
            'release-date' => '2018-04-07T20:44:57+00:00',
            'trigger-condition' => 'member-oauth2-action[login]'
        );
    }

    public function priority()
    {
        return self::kHIGH;
    }

    public static function getSource()
    {
        return extension_members_login_oauth2::EXT_NAME;
    }

    public static function allowEditorToParse()
    {
        return false;
    }

    public function load()
    {
        //try {
            return $this->trigger();
        //} catch (Exception $ex) {
        //    if (Symphony::Log()) {
        //        Symphony::Log()->pushExceptionToLog($ex, true);
        //    }
        //}
    }

    public function trigger()
    {
        $OAUTH2_CONSUMER_KEY = Symphony::Configuration()->get('key', 'members_oauth2_login');
        $OAUTH2_CONSUMER_SECRET = Symphony::Configuration()->get('secret', 'members_oauth2_login');
        if (!$OAUTH2_CONSUMER_KEY || !$OAUTH2_CONSUMER_SECRET) {
            throw new Exception('Could not find either the key or the secret');
        }
        $provider = new \League\OAuth2\Client\Provider\GenericProvider([
            'clientId' => $OAUTH2_CONSUMER_KEY,
            'clientSecret' => $OAUTH2_CONSUMER_SECRET,
            'redirectUri' => Symphony::Configuration()->get('redirect-uri', 'members_oauth2_login'),
            'urlAuthorize' => Symphony::Configuration()->get('url-authorize', 'members_oauth2_login'),
            'urlAccessToken' => Symphony::Configuration()->get('url-access-token', 'members_oauth2_login'),
            'urlResourceOwnerDetails' => Symphony::Configuration()->get('url-resource-owner', 'members_oauth2_login'),
        ]);
        // login
        if (is_array($_POST['member-oauth2-action']) && isset($_POST['member-oauth2-action']['login'])) {
            $_SESSION['OAUTH_SERVICE'] = 'oauth2';
            $_SESSION['OAUTH_START_URL'] = $_POST['redirect'];
            $_SESSION['OAUTH_MEMBERS_SECTION_ID'] = empty($_POST['members-section-id']) ? null : General::intval($_REQUEST['members-section-id']);
            $_SESSION['OAUTH_TOKEN'] = null;
            $_SESSION['OAUTH_STATE'] = $provider->getState();

            redirect($provider->getAuthorizationUrl());
        // Code validation
        } elseif (!empty($_GET['code']) && !empty($_GET['state'])) {
            if (empty($_SESSION['OAUTH_STATE']) || $_GET['state'] !== $_SESSION['OAUTH_STATE']) {
                throw new Exception('Invalid state!');
            }

            $accessToken = $provider->getAccessToken('authorization_code', [
                'code' => $_GET['code']
            ]);

            if (!$accessToken) {
                throw new Exception('Could not get access token');
            }

            if (is_object($response) && isset($response->screen_name)) {
                $_SESSION['OAUTH_TIMESTAMP'] = time();
                $_SESSION['ACCESS_TOKEN'] = $access_token_response['oauth_token'];
                $_SESSION['ACCESS_TOKEN_SECRET'] = $access_token_response['oauth_token_secret'];
                $_SESSION['OAUTH_USER_ID'] = $access_token_response['user_id'];
                $_SESSION['OAUTH_USER_EMAIL'] = $response->email;
                $_SESSION['OAUTH_USER_NAME'] = $response->screen_name;
                $_SESSION['OAUTH_USER_IMG'] = $response->profile_image_url;
                $_SESSION['OAUTH_USER_CITY'] = $response->location;
                $edriver = Symphony::ExtensionManager()->create('members');
                $edriver->setMembersSection($_SESSION['OAUTH_MEMBERS_SECTION_ID']);
                $femail = $edriver->getField('email');
                $mdriver = $edriver->getMemberDriver();
                $email = $response->email;
                if (!$email) {
                    $email = "oauth2" . $response->screen_name . ".com";
                }
                $m = $femail->fetchMemberIDBy($email);
                if (!$m) {
                    $m = new Entry();
                    $m->set('section_id', $_SESSION['OAUTH_MEMBERS_SECTION_ID']);
                    $m->setData($femail->get('id'), array('value' => $email));
                    $mfHandle = Symphony::Configuration()->get('oauth2-handle-field', 'members_oauth2_login');
                    if ($mfHandle) {
                        $m->setData(General::intval($mfHandle), array(
                            'value' => $response->screen_name,
                        ));
                    }
                    $m->commit();
                    $m = $m->get('id');
                }
                $_SESSION['OAUTH_MEMBER_ID'] = $m;
                $login = $mdriver->login(array(
                    'email' => $email
                ));
                if ($login) {
                    redirect($_SESSION['OAUTH_START_URL']);
                } else {
                    throw new Exception('oAuth 2 login failed');
                }
            } else {
                $_SESSION['OAUTH_SERVICE'] = null;
                $_SESSION['ACCESS_TOKEN'] = null;
                $_SESSION['OAUTH_TIMESTAMP'] = 0;
                $_SESSION['OAUTH_MEMBER_ID'] = null;
                session_destroy();
            }
        // logout
        } elseif (is_array($_POST['member-oauth2-action']) && isset($_POST['member-oauth2-action']['logout']) ||
                  is_array($_POST['member-action']) && isset($_POST['member-action']['logout'])) {
            $_SESSION['OAUTH_SERVICE'] = null;
            $_SESSION['OAUTH_START_URL'] = null;
            $_SESSION['OAUTH_MEMBERS_SECTION_ID'] = null;
            $_SESSION['OAUTH_TOKEN'] = null;
            session_destroy();
        }
    }
}

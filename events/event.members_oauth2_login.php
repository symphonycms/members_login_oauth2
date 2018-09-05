<?php

require_once(TOOLKIT . '/class.event.php');
require_once(EXTENSIONS . '/members_login_oauth2/extension.driver.php');
require_once(EXTENSIONS . '/members_login_oauth2/vendor/autoload.php');

class eventmembers_oauth2_login extends Event
{
    const SETTINGS_GROUP = 'members_oauth2_login';

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
        try {
            return $this->trigger();
        } catch (Exception $ex) {
            if (Symphony::Log()) {
                Symphony::Log()->pushExceptionToLog($ex, true);
            }
        }
    }

    public function trigger()
    {
        $OAUTH2_CONSUMER_KEY = Symphony::Configuration()->get('key', self::SETTINGS_GROUP);
        $OAUTH2_CONSUMER_SECRET = Symphony::Configuration()->get('secret', self::SETTINGS_GROUP);
        if (!$OAUTH2_CONSUMER_KEY || !$OAUTH2_CONSUMER_SECRET) {
            throw new Exception('Could not find either the key or the secret');
        }
        $provider = new \League\OAuth2\Client\Provider\GenericProvider([
            'clientId' => $OAUTH2_CONSUMER_KEY,
            'clientSecret' => $OAUTH2_CONSUMER_SECRET,
            'redirectUri' => Symphony::Configuration()->get('redirect-uri', self::SETTINGS_GROUP),
            'urlAuthorize' => Symphony::Configuration()->get('url-authorize', self::SETTINGS_GROUP),
            'urlAccessToken' => Symphony::Configuration()->get('url-access-token', self::SETTINGS_GROUP),
            'urlResourceOwnerDetails' => Symphony::Configuration()->get('url-resource-owner', self::SETTINGS_GROUP),
        ]);
        // login
        if (is_array($_POST['member-oauth2-action']) && isset($_POST['member-oauth2-action']['login'])) {
            // Set options
            $_SESSION['OAUTH_SERVICE'] = 'oauth2';
            $_SESSION['OAUTH_START_URL'] = $_POST['redirect'];
            $_SESSION['OAUTH_MEMBERS_SECTION_ID'] = empty($_POST['members-section-id']) ? null : General::intval($_REQUEST['members-section-id']);
            // Clean up
            $_SESSION['OAUTH_TOKEN'] = null;
            // This also generates from states and needs to be called first
            $authorizationUrl = $provider->getAuthorizationUrl();
            $_SESSION['OAUTH_STATE'] = $provider->getState();
            // Redirect to login
            redirect($authorizationUrl);
        // Code validation
        } elseif (!empty($_GET['code']) && !empty($_GET['state'])) {
            if (empty($_SESSION['OAUTH_STATE']) || $_GET['state'] !== $_SESSION['OAUTH_STATE']) {
                throw new Exception('Invalid state!');
            }

            $accessToken = $provider->getAccessToken('authorization_code', [
                'code' => $_GET['code']
            ]);

            if (!$accessToken || !($token = $accessToken->getToken())) {
                throw new Exception('Could not get access token');
            }

            // Start login process
            $_SESSION['OAUTH_TIMESTAMP'] = time();
            $_SESSION['ACCESS_TOKEN'] = $token;
            $_SESSION['REFRESH_TOKEN'] = $accessToken->getRefreshToken();

            // Get user info
            $resourceOwner = $provider->getResourceOwner($accessToken);
            $owner = $resourceOwner->toArray();

            // Get members extensions infos
            $edriver = Symphony::ExtensionManager()->create('members');
            $edriver->setMembersSection($_SESSION['OAUTH_MEMBERS_SECTION_ID']);
            $fpass = $edriver->getField('authentication');
            if ($fpass) {
                throw new Exception('Your member section cannot contain a password field. Please a member section without one.');
            }
            $femail = $edriver->getField('identity');
            if (!$femail) {
                $femail = $edriver->getField('email');
            }
            if (!$femail) {
                throw new Exception('Your member section does not have an identity (username/email) field. Please add one.');
            }
            $mdriver = $edriver->getMemberDriver();
            $email = $owner['mail'];
            if (!$email) {
                throw new Exception('User does not have an email, can not continue.');
            }

            // Try to find member
            $m = $femail->fetchMemberIDBy($email);
            // Clean global errors
            extension_Members::$_errors = array();
            if (!$m) {
                // Create new member
                $m = new Entry();
                $m->set('section_id', $_SESSION['OAUTH_MEMBERS_SECTION_ID']);
                $fdata = array('value' => $email);
                if ($femail instanceof fieldMemberUsername) {
                    $fdata['handle'] = Lang::createHandle($email);
                }
                $m->setData($femail->get('id'), $fdata);
                $m->commit();
                $m = $m->get('id');
            }
            // Set the id in session to make login work
            $_SESSION['OAUTH_MEMBER_ID'] = $m;
            // Login the user
            $ldata = array(
                'email' => $email,
                'username' => $email,
            );
            $login = $mdriver->login($ldata);
            // If it worked
            if ($login) {
                // Set the other login info
                $_SESSION['OAUTH_USER_ID'] = $accessToken->getResourceOwnerId();
                $_SESSION['OAUTH_USER_EMAIL'] = $email;
                $_SESSION['OAUTH_USER_NAME'] = null;
                $_SESSION['OAUTH_USER_IMG'] = null;
                $_SESSION['OAUTH_USER_CITY'] = null;
                redirect($_SESSION['OAUTH_START_URL']);
            } else {
                unset($_SESSION['OAUTH_MEMBER_ID']);
                unset($_SESSION['ACCESS_TOKEN']);
                unset($_SESSION['REFRESH_TOKEN']);
                throw new Exception('oAuth 2 login failed ' . current(extension_Members::$_errors));
            }
        // logout
        } elseif (is_array($_POST['member-oauth2-action']) && isset($_POST['member-oauth2-action']['logout']) ||
                  is_array($_POST['member-action']) && isset($_POST['member-action']['logout'])) {
            $_SESSION['OAUTH_SERVICE'] = null;
            $_SESSION['OAUTH_START_URL'] = null;
            $_SESSION['OAUTH_MEMBERS_SECTION_ID'] = null;
            $_SESSION['OAUTH_TOKEN'] = null;
            $_SESSION['ACCESS_TOKEN'] = null;
            $_SESSION['REFRESH_TOKEN'] = null;
            $_SESSION['OAUTH_MEMBER_ID'] = null;
            session_destroy();
        }
    }
}

# Members: oAuth 2 Login

> Logs in users using oAuth 2

### SPECS ###

Automatically creates account and logs in the user.
It uses the [oAuth 2 client from The PHP League](https://github.com/thephpleague/oauth2-client).

### REQUIREMENTS ###

- Symphony CMS version 2.7.x and up (as of the day of the last release of this extension)
- Members extension version 1.9.0

### INSTALLATION ###

- `git clone` / download and unpack the tarball file
- Put into the extension directory
- Enable/install just like any other extension

You can also install it using the [extension downloader](http://symphonyextensions.com/extensions/extension_downloader/).

For more information, see <https://www.getsymphony.com/learn/tasks/view/install-an-extension/>

### HOW TO USE ###

- Enable the extension
- Create a new Member section with only a email field (no password)
	- Optionally, create a input/textarea/textbox field for the oAuth 2 username, avatar and refresh token.
- Set the required configuration values:

```php
###### MEMBERS_OAUTH2_LOGIN ######
'members_oauth2_login' => array(
    'key' => 'REPLACE ME',
    'secret' => 'REPLACE ME',
    'redirect-uri' => 'https://yoursite.example.com/your-redirect-url/',
    'url-authorize' => 'https://provider.example.com/your-authorize-url/',
    'url-access-token' => 'https://provider.example.com/your-access-token-url/',
    'url-resource-owner' => 'https://provider.example.com/your-resource-owner-url/',
    'oauth2-email-field' => 'REPLACE ME with a field id if you want to save the oauth2 email',
    'oauth2-username-field' => 'REPLACE ME with a field id if you want to save the oauth2 username',
    'oauth2-avatar-field' => 'REPLACE ME with a field id if you want to save the oauth2 avatar',
    'oauth2-refresh-token' => 'REPLACE ME with a field id if you want to save the oauth2 refresh token',
),
########
```

- Create a "oAuth2" page and attach the "Members: oAuth 2 login" event on it.
- Create the login form:

```html
<form action="/oauth2/" method="POST">
	<input type="hidden" name="redirect" value="/oauth2/" />
	<input type="hidden" name="members-section-id" value="<Your section id>" />
	<input type="hidden" name="member-oauth2-action[login]" value="Login" />
	<button>Log in with oAuth 2 Provider</button>
</form>
```

This form will redirect the user to your oauth2 login page.
After the users logs in, your oauth 2 service will redirect the user your redirect uri.

- Add another form to handle the actual log in process when the user comes back from oauth2 on your redirect uri.

- To make this process transparent for the end user, this form can be auto-submitted via javascript.

```xslt
<xsl:if test="string-length(/data/params/url-oauth-token) != 0">
    <form id="oauth2form" method="POST" action="{$current-url}/">
        <input type="hidden" name="oauth_token" value="{/data/params/url-oauth-token}" />
        <input type="hidden" name="oauth_verifier" value="{/data/params/url-oauth-verifier}" />
        <button>Validate</button>
    </form>
    <script>if (window.oauth2form) oauth2form.submit();</script>
</xsl:if>
```

- If everything works, the user will be redirected to the 'redirect' value, just like the standard Members login.

- To log out the user, use the default member logout or add this form to any page:

```xslt
<form id="oauth2logoutform" method="POST" action="{$current-url}/">
    <input type="hidden" name="member-oauth2-action[logout]" value="Logout" />
    <button>Logout</button>
</form>
```

### SPONSOR ###

Thanks to [Wannes Debusschere](https://github.com/wdebusschere) for its financial contribution leading to the initial development of this extension.

### LICENSE ###

MIT <https://symphonycms.mit-license.org>

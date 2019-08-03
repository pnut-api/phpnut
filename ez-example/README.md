ezphpnut Example Site
=====================

Example starter site for the pnut.io Stream API, using the EZ/easy version of the phpnut client.

If you are planning to design an app for viewing within a browser that requires a login screen etc, this is a great place to start. The ezphpnut class aims to hide all the nasty authentication stuff from the average developer. It is also recommended that you start here if you have never worked with OAuth and/or APIs before.

More info on the phpnut PHP client at <a href="https://github.com/pnut-api/phpnut">https://github.com/pnut-api/phpnut</a>.

More info on the pnut.io Stream API at <a href="https://github.com/pnut-api/api-spec">https://github.com/pnut-api/api-spec</a>.

**Contributors:**
* <a href="https://alpha.app.net/jdolitsky" target="_blank">@jdolitsky</a>
* <a href="https://pnut.io/@ravisorg" target="_blank">@ravisorg</a>
* <a href="https://github.com/wpstudio" target="_blank">@wpstudio</a>
* <a href="https://alpha.app.net/harold" target="_blank">@harold</a>
* <a href="https://alpha.app.net/hxf148" target="_blank">@hxf148</a>
* <a href="https://alpha.app.net/edent" target="_blank">@edent</a>
* <a href="https://pnut.io/@c" target="_blank">@cdn</a>
* <a href="https://pnut.io/@ryantharp" target="_blank">@ryantharp</a>
* <a href="https://pnut.io/@33mhz" target="_blank">@33MHz</a>

Usage:
--------

1. Copy this **ez-example** directory into your web root.
2. Run composer to download dependencies in the ez-example directory:
  ```sh
  cd ez-example
  composer update
  ```
3. Rename the **ez-settings.sample.php** file to **ez-settings.php** and edit the values to reflect the ones for your app (to make things easy, change the Callback URL within your pnut.io developers console to http://localhost/ez-example/callback.php). Add or remove values from the $app_scope array to change the permissions your app will have with the authenticated user.
4. You should now be able to visit http://localhost/ez-example/ with your browser and click 'Sign in with pnut.io'.

Copyright (c) 2013, Josh Dolitsky
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:
    * Redistributions of source code must retain the above copyright
      notice, this list of conditions and the following disclaimer.
    * Redistributions in binary form must reproduce the above copyright
      notice, this list of conditions and the following disclaimer in the
      documentation and/or other materials provided with the distribution.
    * Neither the name of the Josh Dolitsky nor the names of its 
      contributors may be used to endorse or promote products derived 
      from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL TRAVIS RICHARDSON BE LIABLE FOR ANY
DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

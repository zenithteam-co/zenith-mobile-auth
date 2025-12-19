**Zenith Mobile Auth (OTP)**

**Zenith Mobile Auth** is a WordPress plugin that replaces the standard WooCommerce login and registration forms with a secure, mobile-number-based OTP (One-Time Password) system. Designed for the Iranian market with support for IPPanel.

**Features**

- **Mobile-Only Authentication:** Users login/register using only their phone number.
- **OTP Verification:** Secure 4-6 digit code verification via SMS.
- **IPPanel Integration:** Native support for IPPanel (and Edge API) Pattern sending.
- **WooCommerce Ready:** Seamlessly integrates with Checkout and My Account pages.
- **Profile Completion:** Option to ask for Name, Family Name, and Gender after verification.
- **Visual Customizer:** Live preview admin panel to style the login box (Elementor-style controls).
- **Security:**
    - Rate limiting per phone number.
    - Wait timer between resend requests.
    - Session hijacking protection (Nonce/Token matching).
    - WebOTP API support (Auto-read SMS on Android).

**Installation**

1.  Download the latest release from the \[suspicious link removed\] page.
2.  Upload the zip file to your WordPress **Plugins > Add New** section.
3.  Activate the plugin.
4.  Go to **Settings > Zenith Mobile Auth**.
5.  Enter your IPPanel **API Key**, **Originator**, and **Pattern Code**.

**Configuration**

**SMS Pattern**

Create a pattern in your IPPanel dashboard with the following format to support Auto-fill: Your verification code is %code%. \\n @yourdomain.com #%code%

**Settings**

- **General:** API keys and Pattern variables.
- **Registration:** Enable/Disable Name and Gender fields.
- **Style:** Customize colors, borders, and spacing with a live preview.
- **Security:** Set daily limits and resend timers.

**Updates**

This plugin supports automatic updates via GitHub. When a new release is tagged on this repository, WordPress will detect the update.

**Credits**

Developed by [Mahdi Soltani](https://www.google.com/search?q=https://zenithteam.co/mahdi-soltani) | [Zenith Team](https://zenithteam.co).

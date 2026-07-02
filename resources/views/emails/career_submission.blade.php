<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $jobtitle }} - Application</title>
</head>
<body style="margin: 0; padding: 0; font-family: Verdana, sans-serif; background-color: #DCDDE1;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #DCDDE1;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="width: 100%; max-width: 600px; margin: 0 auto;">
                    <tbody>
                        <tr>
                       <td align="center" style="padding:0;">
                               <a href='https://codeandcore.com/' style='width: 600px; display: block;'>
                                                                    <img src='https://codeandcore.sirv.com/newsletter/contact-top-banner.png' alt='Code and Core Contact Form Submission' style='display: block;' />
                                                                </a>
                            </td>
                        </tr>
                        <tr>
                            <td align="left" bgcolor="#FFFFFF" style="padding: 50px 30px;">
                                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                    <tbody>
                                        <tr>
                                            <td align="center">
                                                <h1 style="margin: 0; font-weight: 600; font-size: 24px; line-height: 30px; color: #1A0726; text-transform: capitalize;">{{ $jobtitle }}</h1>
                                                <br><br>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td align="left">
                                                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                                    <tbody>
                                                        @if(isset($name))
                                                        <tr>
                                                            <td width="35%" align="left" valign="middle" style="border-bottom: 1px solid #1A0726; padding: 7px 0;">
                                                                <table cellpadding="0" cellspacing="0" border="0">
                                                                    <tr>
                                                                        <td width="20px"><img src="https://codeandcore.sirv.com/newsletter/dot.png" alt="dot" style="display: block;" /></td>
                                                                        <td><p style="margin: 0; font-weight: 400; font-size: 14px; color: #2C2C2C;">Name</p></td>
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                            <td width="2%" style="border-bottom: 1px solid #1A0726; padding: 7px 0;" valign="middle"><p style="margin: 0; font-size: 14px; color: #2C2C2C;">:</p></td>
                                                            <td width="63%" align="left" valign="middle" style="border-bottom: 1px solid #1A0726; padding: 7px 0;"><p style="margin: 0; font-size: 14px; color: #2C2C2C;">{{ $name }}</p></td>
                                                        </tr>
                                                        @endif

                                                        @if(isset($email))
                                                        <tr>
                                                            <td width="35%" align="left" valign="middle" style="border-bottom: 1px solid #1A0726; padding: 7px 0;">
                                                                <table cellpadding="0" cellspacing="0" border="0">
                                                                    <tr>
                                                                        <td width="20px"><img src="https://codeandcore.sirv.com/newsletter/dot.png" alt="dot" style="display: block;" /></td>
                                                                        <td><p style="margin: 0; font-weight: 400; font-size: 14px; color: #2C2C2C;">Email</p></td>
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                            <td width="2%" style="border-bottom: 1px solid #1A0726; padding: 7px 0;" valign="middle"><p style="margin: 0; font-size: 14px; color: #2C2C2C;">:</p></td>
                                                            <td width="63%" align="left" valign="middle" style="border-bottom: 1px solid #1A0726; padding: 7px 0;"><p style="margin: 0; font-size: 14px; color: #2C2C2C;">{{ $email }}</p></td>
                                                        </tr>
                                                        @endif

                                                        @if(isset($phone))
                                                        <tr>
                                                            <td width="35%" align="left" valign="middle" style="border-bottom: 1px solid #1A0726; padding: 7px 0;">
                                                                <table cellpadding="0" cellspacing="0" border="0">
                                                                    <tr>
                                                                        <td width="20px"><img src="https://codeandcore.sirv.com/newsletter/dot.png" alt="dot" style="display: block;" /></td>
                                                                        <td><p style="margin: 0; font-weight: 400; font-size: 14px; color: #2C2C2C;">Phone</p></td>
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                            <td width="2%" style="border-bottom: 1px solid #1A0726; padding: 7px 0;" valign="middle"><p style="margin: 0; font-size: 14px; color: #2C2C2C;">:</p></td>
                                                            <td width="63%" align="left" valign="middle" style="border-bottom: 1px solid #1A0726; padding: 7px 0;"><p style="margin: 0; font-size: 14px; color: #2C2C2C;">{{ $phone }}</p></td>
                                                        </tr>
                                                        @endif

                                                        @if(isset($experience))
                                                        <tr>
                                                            <td width="35%" align="left" valign="middle" style="border-bottom: 1px solid #1A0726; padding: 7px 0;">
                                                                <table cellpadding="0" cellspacing="0" border="0">
                                                                    <tr>
                                                                        <td width="20px"><img src="https://codeandcore.sirv.com/newsletter/dot.png" alt="dot" style="display: block;" /></td>
                                                                        <td><p style="margin: 0; font-weight: 400; font-size: 14px; color: #2C2C2C;">Experience</p></td>
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                            <td width="2%" style="border-bottom: 1px solid #1A0726; padding: 7px 0;" valign="middle"><p style="margin: 0; font-size: 14px; color: #2C2C2C;">:</p></td>
                                                            <td width="63%" align="left" valign="middle" style="border-bottom: 1px solid #1A0726; padding: 7px 0;"><p style="margin: 0; font-size: 14px; color: #2C2C2C;">{{ $experience }}</p></td>
                                                        </tr>
                                                        @endif

                                                        @if(isset($resume_url))
                                                        <tr>
                                                            <td width="35%" align="left" valign="middle" style="border-bottom: 1px solid #1A0726; padding: 7px 0;">
                                                                <table cellpadding="0" cellspacing="0" border="0">
                                                                    <tr>
                                                                        <td width="20px"><img src="https://codeandcore.sirv.com/newsletter/dot.png" alt="dot" style="display: block;" /></td>
                                                                        <td><p style="margin: 0; font-weight: 400; font-size: 14px; color: #2C2C2C;">Resume URL</p></td>
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                            <td width="2%" style="border-bottom: 1px solid #1A0726; padding: 7px 0;" valign="middle"><p style="margin: 0; font-size: 14px; color: #2C2C2C;">:</p></td>
                                                            <td width="63%" align="left" valign="middle" style="border-bottom: 1px solid #1A0726; padding: 7px 0;"><p style="margin: 0; font-size: 14px; color: #2C2C2C;"><a href="{{ $resume_url }}" target="_blank">Click Here</a></p></td>
                                                        </tr>
                                                        @endif

                                                        @if(isset($current_ctc))
                                                        <tr>
                                                            <td width="35%" align="left" valign="middle" style="border-bottom: 1px solid #1A0726; padding: 7px 0;">
                                                                <table cellpadding="0" cellspacing="0" border="0">
                                                                    <tr>
                                                                        <td width="20px"><img src="https://codeandcore.sirv.com/newsletter/dot.png" alt="dot" style="display: block;" /></td>
                                                                        <td><p style="margin: 0; font-weight: 400; font-size: 14px; color: #2C2C2C;">Current CTC</p></td>
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                            <td width="2%" style="border-bottom: 1px solid #1A0726; padding: 7px 0;" valign="middle"><p style="margin: 0; font-size: 14px; color: #2C2C2C;">:</p></td>
                                                            <td width="63%" align="left" valign="middle" style="border-bottom: 1px solid #1A0726; padding: 7px 0;"><p style="margin: 0; font-size: 14px; color: #2C2C2C;">{{ $current_ctc }}</p></td>
                                                        </tr>
                                                        @endif

                                                        @if(isset($expected_ctc))
                                                        <tr>
                                                            <td width="35%" align="left" valign="middle" style="border-bottom: 1px solid #1A0726; padding: 7px 0;">
                                                                <table cellpadding="0" cellspacing="0" border="0">
                                                                    <tr>
                                                                        <td width="20px"><img src="https://codeandcore.sirv.com/newsletter/dot.png" alt="dot" style="display: block;" /></td>
                                                                        <td><p style="margin: 0; font-weight: 400; font-size: 14px; color: #2C2C2C;">Expected CTC</p></td>
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                            <td width="2%" style="border-bottom: 1px solid #1A0726; padding: 7px 0;" valign="middle"><p style="margin: 0; font-size: 14px; color: #2C2C2C;">:</p></td>
                                                            <td width="63%" align="left" valign="middle" style="border-bottom: 1px solid #1A0726; padding: 7px 0;"><p style="margin: 0; font-size: 14px; color: #2C2C2C;">{{ $expected_ctc }}</p></td>
                                                        </tr>
                                                        @endif

                                                        @if(isset($notice_period))
                                                        <tr>
                                                            <td width="35%" align="left" valign="middle" style="padding: 7px 0;">
                                                                <table cellpadding="0" cellspacing="0" border="0">
                                                                    <tr>
                                                                        <td width="20px"><img src="https://codeandcore.sirv.com/newsletter/dot.png" alt="dot" style="display: block;" /></td>
                                                                        <td><p style="margin: 0; font-weight: 400; font-size: 14px; color: #2C2C2C;">Notice Period</p></td>
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                            <td width="2%" style="padding: 7px 0;" valign="middle"><p style="margin: 0; font-size: 14px; color: #2C2C2C;">:</p></td>
                                                            <td width="63%" align="left" valign="middle" style="padding: 7px 0;"><p style="margin: 0; font-size: 14px; color: #2C2C2C;">{{ $notice_period }}</p></td>
                                                        </tr>
                                                        @endif
                                                    </tbody>
                                                </table>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td align="left" bgcolor="#F6F5FF" style="padding: 50px 70px;">
                                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                    <tbody>
                                        <tr>
                                            <td align="center">
                                                <table cellpadding="0" cellspacing="0" border="0">
                                                    <tr>
                                                        <td style="padding: 0 5px;"><a href="https://www.facebook.com/codeandcore" target="_blank"><img src="https://codeandcore.sirv.com/newsletter/facebook.png" alt="FB" width="30" /></a></td>
                                                        <td style="padding: 0 5px;"><a href="https://www.instagram.com/codeandcore" target="_blank"><img src="https://codeandcore.sirv.com/newsletter/instagram.png" alt="IG" width="30" /></a></td>
                                                        <td style="padding: 0 5px;"><a href="https://www.youtube.com/@codeandcoreofficial" target="_blank"><img src="https://codeandcore.sirv.com/newsletter/youtube.png" alt="YT" width="30" /></a></td>
                                                        <td style="padding: 0 5px;"><a href="https://www.linkedin.com/company/code-and-core" target="_blank"><img src="https://codeandcore.sirv.com/newsletter/linkedin.png" alt="IN" width="30" /></a></td>
                                                        <td style="padding: 0 5px;"><a href="mailto:codeandcore@gmail.com" target="_blank"><img src="https://codeandcore.sirv.com/newsletter/email.png" alt="Email" width="30" /></a></td>
                                                        <td style="padding: 0 5px;"><a href="https://codeandcore.com/" target="_blank"><img src="https://codeandcore.sirv.com/newsletter/website.png" alt="Web" width="30" /></a></td>
                                                        <td style="padding: 0 5px;"><a href="https://x.com/codeandcore" target="_blank"><img src="https://codeandcore.sirv.com/newsletter/x.png" alt="X" width="30" /></a></td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                        <tr><td height="35"></td></tr>
                                        <tr>
                                            <td align="center">
                                                <a href="https://codeandcore.com/" target="_blank">
                                                    <img src="https://codeandcore.sirv.com/newsletter/cnc.png" alt="Code and Core" width="230" />
                                                </a>
                                            </td>
                                        </tr>
                                        <tr><td height="25"></td></tr>
                                        <tr>
                                            <td align="center">
                                                <p style="margin: 10px 0; font-size: 14px; color: #2C2C2C;">422,423,410 - S.V. Square commercial building, New Ranip, Ahmedabad, Gujrat, India.</p>
                                                <p style="margin: 0; font-size: 14px; color: #2C2C2C;">&copy; {{ date('Y') }} Code and core Tech LLP</p>
                                            </td>
                                        </tr>
                                        <tr><td height="25"></td></tr>
                                        <tr>
                                            <td align="center">
                                                <p style="margin: 0; font-size: 14px; color: #2C2C2C;">
                                                    <a href="https://codeandcore.com/contact-us/" style="color: #2C2C2C; text-decoration: underline;">Contact Us</a> | 
                                                    <a href="https://codeandcore.com/privacy-policy/" style="color: #2C2C2C; text-decoration: underline;">Privacy Policy</a>
                                                </p>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

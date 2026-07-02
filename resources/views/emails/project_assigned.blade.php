<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Project Assignment</title>
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
                                                <h1 style="margin: 0; font-weight: 600; font-size: 24px; line-height: 30px; color: #1A0726; text-transform: capitalize;">New Project Assignment</h1>
                                                <br><br>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td align="left">
                                                <p style="margin: 0 0 20px 0; font-size: 14px; color: #2C2C2C;">You have been assigned to a new project. Here are the details:</p>
                                                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                                    <tbody>
                                                        <!-- Project Name -->
                                                        <tr>
                                                            <td width="35%" align="left" valign="middle" style="border-bottom: 1px solid #1A0726; padding: 7px 0;">
                                                                <table cellpadding="0" cellspacing="0" border="0">
                                                                    <tr>
                                                                        <td width="20px"><img src="https://codeandcore.sirv.com/newsletter/dot.png" alt="dot" style="display: block;" /></td>
                                                                        <td><p style="margin: 0; font-weight: 400; font-size: 14px; color: #2C2C2C;">Project Name</p></td>
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                            <td width="2%" style="border-bottom: 1px solid #1A0726; padding: 7px 0;" valign="middle">
                                                                <p style="margin: 0; font-weight: 400; font-size: 14px; color: #2C2C2C;">:</p>
                                                            </td>
                                                            <td width="63%" align="left" valign="middle" style="border-bottom: 1px solid #1A0726; padding: 7px 0;">
                                                                <p style="margin: 0; font-weight: 400; font-size: 14px; color: #2C2C2C;">{{ $project->project_name }}</p>
                                                            </td>
                                                        </tr>
                                                        <!-- Project Industry -->
                                                        <tr>
                                                            <td width="35%" align="left" valign="middle" style="border-bottom: 1px solid #1A0726; padding: 7px 0;">
                                                                <table cellpadding="0" cellspacing="0" border="0">
                                                                    <tr>
                                                                        <td width="20px"><img src="https://codeandcore.sirv.com/newsletter/dot.png" alt="dot" style="display: block;" /></td>
                                                                        <td><p style="margin: 0; font-weight: 400; font-size: 14px; color: #2C2C2C;">Industry</p></td>
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                            <td width="2%" style="border-bottom: 1px solid #1A0726; padding: 7px 0;" valign="middle">
                                                                <p style="margin: 0; font-weight: 400; font-size: 14px; color: #2C2C2C;">:</p>
                                                            </td>
                                                            <td width="63%" align="left" valign="middle" style="border-bottom: 1px solid #1A0726; padding: 7px 0;">
                                                                <p style="margin: 0; font-weight: 400; font-size: 14px; color: #2C2C2C;">{{ $project->project_industry }}</p>
                                                            </td>
                                                        </tr>
                                                        <!-- Timeline -->
                                                        <tr>
                                                            <td width="35%" align="left" valign="middle" style="border-bottom: 1px solid #1A0726; padding: 7px 0;">
                                                                <table cellpadding="0" cellspacing="0" border="0">
                                                                    <tr>
                                                                        <td width="20px"><img src="https://codeandcore.sirv.com/newsletter/dot.png" alt="dot" style="display: block;" /></td>
                                                                        <td><p style="margin: 0; font-weight: 400; font-size: 14px; color: #2C2C2C;">Timeline</p></td>
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                            <td width="2%" style="border-bottom: 1px solid #1A0726; padding: 7px 0;" valign="middle">
                                                                <p style="margin: 0; font-weight: 400; font-size: 14px; color: #2C2C2C;">:</p>
                                                            </td>
                                                            <td width="63%" align="left" valign="middle" style="border-bottom: 1px solid #1A0726; padding: 7px 0;">
                                                                <p style="margin: 0; font-weight: 400; font-size: 14px; color: #2C2C2C;">{{ $project->estimated_start_date }} to {{ $project->estimated_end_date }}</p>
                                                            </td>
                                                        </tr>
                                                        <!-- Priority -->
                                                        <tr>
                                                            <td width="35%" align="left" valign="middle" style="border-bottom: 1px solid #1A0726; padding: 7px 0;">
                                                                <table cellpadding="0" cellspacing="0" border="0">
                                                                    <tr>
                                                                        <td width="20px"><img src="https://codeandcore.sirv.com/newsletter/dot.png" alt="dot" style="display: block;" /></td>
                                                                        <td><p style="margin: 0; font-weight: 400; font-size: 14px; color: #2C2C2C;">Priority</p></td>
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                            <td width="2%" style="border-bottom: 1px solid #1A0726; padding: 7px 0;" valign="middle">
                                                                <p style="margin: 0; font-weight: 400; font-size: 14px; color: #2C2C2C;">:</p>
                                                            </td>
                                                            <td width="63%" align="left" valign="middle" style="border-bottom: 1px solid #1A0726; padding: 7px 0;">
                                                                <p style="margin: 0; font-weight: 400; font-size: 14px; color: #2C2C2C;">{{ $project->priority }}</p>
                                                            </td>
                                                        </tr>
                                                        <!-- Project Manager -->
                                                        <tr>
                                                            <td width="35%" align="left" valign="middle" style="padding: 7px 0;">
                                                                <table cellpadding="0" cellspacing="0" border="0">
                                                                    <tr>
                                                                        <td width="20px"><img src="https://codeandcore.sirv.com/newsletter/dot.png" alt="dot" style="display: block;" /></td>
                                                                        <td><p style="margin: 0; font-weight: 400; font-size: 14px; color: #2C2C2C;">Manager</p></td>
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                            <td width="2%" style="padding: 7px 0;" valign="middle">
                                                                <p style="margin: 0; font-weight: 400; font-size: 14px; color: #2C2C2C;">:</p>
                                                            </td>
                                                            <td width="63%" align="left" valign="middle" style="padding: 7px 0;">
                                                                <p style="margin: 0; font-weight: 400; font-size: 14px; color: #2C2C2C;">{{ $managerName }}</p>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                                <br>
                                                <div align="center">
                                                    <a href="{{ config('app.front_url') . '/project/info/' . $project->_id }}" style="background-color: #1A0726; color: #ffffff; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: 600; display: inline-block;">View Project Details</a>
                                                </div>
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
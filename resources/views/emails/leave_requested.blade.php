<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Leave Request Generated</title>
</head>
<body style="margin: 0; padding: 0; font-family: Verdana, sans-serif; background-color: #DCDDE1;">
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #DCDDE1;">
        <tr>
            <td align="center" style="padding: 20px 0;">
                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="width: 100%; max-width: 600px; margin: 0 auto;">
                    <tbody>
                        <!-- Header with Logo and #F6F5FF Background -->
                        <tr>
                            <td align="center" bgcolor="#F6F5FF" style="padding: 30px 20px 25px 20px; border-bottom: 1px solid #EAE8F5;">
                                <a href="https://codeandcore.com/" target="_blank" style="text-decoration: none; display: inline-block;">
                                    <img src="https://codeandcore.sirv.com/newsletter/cnc.png" alt="Code and Core" width="200" style="display: block; border: 0;" />
                                </a>
                            </td>
                        </tr>

                        <!-- Main Content with Original Theme -->
                        <tr>
                            <td align="left" bgcolor="#FFFFFF" style="padding: 40px 30px;">
                                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                    <tbody>
                                        <tr>
                                            <td align="center">
                                                <h1 style="margin: 0; font-weight: 600; font-size: 24px; line-height: 30px; color: #1A0726; text-transform: capitalize;">New Leave Request</h1>
                                                <br><br>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td align="left">
                                                <p style="margin: 0 0 20px 0; font-size: 14px; color: #2C2C2C;">Hi,</p>
                                                <p style="margin: 0 0 20px 0; font-size: 14px; color: #2C2C2C;">A new leave request has been submitted. Here are the details:</p>
                                                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                                    <tbody>
                                                        <!-- Employee Name -->
                                                        <tr>
                                                            <td width="35%" align="left" valign="middle" style="border-bottom: 1px solid #1A0726; padding: 10px 0;">
                                                                <p style="margin: 0; font-weight: 600; font-size: 14px; color: #1A0726;">Employee Name</p>
                                                            </td>
                                                            <td width="2%" style="border-bottom: 1px solid #1A0726; padding: 10px 0;" valign="middle">
                                                                <p style="margin: 0; font-weight: 400; font-size: 14px; color: #2C2C2C;">:</p>
                                                            </td>
                                                            <td width="63%" align="left" valign="middle" style="border-bottom: 1px solid #1A0726; padding: 10px 0;">
                                                                <p style="margin: 0; font-weight: 400; font-size: 14px; color: #2C2C2C;">{{ $employee->name ?? '' }} {{ $employee->last_name ?? '' }}</p>
                                                            </td>
                                                        </tr>
                                                        <!-- Leave Type -->
                                                        <tr>
                                                            <td width="35%" align="left" valign="middle" style="border-bottom: 1px solid #1A0726; padding: 10px 0;">
                                                                <p style="margin: 0; font-weight: 600; font-size: 14px; color: #1A0726;">Leave Type</p>
                                                            </td>
                                                            <td width="2%" style="border-bottom: 1px solid #1A0726; padding: 10px 0;" valign="middle">
                                                                <p style="margin: 0; font-weight: 400; font-size: 14px; color: #2C2C2C;">:</p>
                                                            </td>
                                                            <td width="63%" align="left" valign="middle" style="border-bottom: 1px solid #1A0726; padding: 10px 0;">
                                                                <p style="margin: 0; font-weight: 400; font-size: 14px; color: #2C2C2C;">{{ $leave->leave_type ?? 'N/A' }}</p>
                                                            </td>
                                                        </tr>
                                                        <!-- Start Date -->
                                                        <tr>
                                                            <td width="35%" align="left" valign="middle" style="border-bottom: 1px solid #1A0726; padding: 10px 0;">
                                                                <p style="margin: 0; font-weight: 600; font-size: 14px; color: #1A0726;">Start Date</p>
                                                            </td>
                                                            <td width="2%" style="border-bottom: 1px solid #1A0726; padding: 10px 0;" valign="middle">
                                                                <p style="margin: 0; font-weight: 400; font-size: 14px; color: #2C2C2C;">:</p>
                                                            </td>
                                                            <td width="63%" align="left" valign="middle" style="border-bottom: 1px solid #1A0726; padding: 10px 0;">
                                                                <p style="margin: 0; font-weight: 400; font-size: 14px; color: #2C2C2C;">{{ isset($leave->start_date) ? \Carbon\Carbon::parse($leave->start_date)->format('d-m-Y') : 'N/A' }}</p>
                                                            </td>
                                                        </tr>
                                                        <!-- End Date -->
                                                        <tr>
                                                            <td width="35%" align="left" valign="middle" style="border-bottom: 1px solid #1A0726; padding: 10px 0;">
                                                                <p style="margin: 0; font-weight: 600; font-size: 14px; color: #1A0726;">End Date</p>
                                                            </td>
                                                            <td width="2%" style="border-bottom: 1px solid #1A0726; padding: 10px 0;" valign="middle">
                                                                <p style="margin: 0; font-weight: 400; font-size: 14px; color: #2C2C2C;">:</p>
                                                            </td>
                                                            <td width="63%" align="left" valign="middle" style="border-bottom: 1px solid #1A0726; padding: 10px 0;">
                                                                <p style="margin: 0; font-weight: 400; font-size: 14px; color: #2C2C2C;">{{ isset($leave->end_date) ? \Carbon\Carbon::parse($leave->end_date)->format('d-m-Y') : 'N/A' }}</p>
                                                            </td>
                                                        </tr>
                                                        <!-- Duration -->
                                                        <tr>
                                                            <td width="35%" align="left" valign="middle" style="border-bottom: 1px solid #1A0726; padding: 10px 0;">
                                                                <p style="margin: 0; font-weight: 600; font-size: 14px; color: #1A0726;">Duration</p>
                                                            </td>
                                                            <td width="2%" style="border-bottom: 1px solid #1A0726; padding: 10px 0;" valign="middle">
                                                                <p style="margin: 0; font-weight: 400; font-size: 14px; color: #2C2C2C;">:</p>
                                                            </td>
                                                            <td width="63%" align="left" valign="middle" style="border-bottom: 1px solid #1A0726; padding: 10px 0;">
                                                                <p style="margin: 0; font-weight: 400; font-size: 14px; color: #2C2C2C;">{{ $leave->leave_duration ?? 0 }} day(s)@if(!empty($leave->half_day) && !empty($leave->half_day_type)) ({{ ucwords(str_replace('_', ' ', $leave->half_day_type)) }})@endif</p>
                                                            </td>
                                                        </tr>
                                                        <!-- Reason -->
                                                        <tr>
                                                            <td width="35%" align="left" valign="middle" style="padding: 10px 0;">
                                                                <p style="margin: 0; font-weight: 600; font-size: 14px; color: #1A0726;">Reason</p>
                                                            </td>
                                                            <td width="2%" style="padding: 10px 0;" valign="middle">
                                                                <p style="margin: 0; font-weight: 400; font-size: 14px; color: #2C2C2C;">:</p>
                                                            </td>
                                                            <td width="63%" align="left" valign="middle" style="padding: 10px 0;">
                                                                <p style="margin: 0; font-weight: 400; font-size: 14px; color: #2C2C2C;">{{ $leave->reason ?? 'Not specified' }}</p>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                                <p style="margin: 25px 0 0 0; font-size: 14px; color: #2C2C2C;">Please review and take necessary action from the admin dashboard.</p>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </td>
                        </tr>

                        <!-- Footer with original text & styling -->
                        <tr>
                            <td align="center" bgcolor="#F6F5FF" style="padding: 35px 40px; border-top: 1px solid #EAE8F5;">
                                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                    <tbody>
                                        <tr>
                                            <td align="center">
                                                <p style="margin: 0 0 10px 0; font-size: 14px; line-height: 22px; color: #2C2C2C; text-align: center;">422,423,410 - S.V. Square commercial building, New Ranip, Ahmedabad, Gujrat, India.</p>
                                                <p style="margin: 0 0 20px 0; font-size: 14px; color: #2C2C2C; text-align: center;">&copy; {{ date('Y') }} Code and Core Tech LLP</p>
                                                <p style="margin: 0; font-size: 14px; text-align: center;">
                                                    <a href="https://codeandcore.com/contact-us/" target="_blank" style="color: #2C2C2C; text-decoration: underline;">Contact Us</a>
                                                    <span style="color: #2C2C2C; margin: 0 5px;">|</span>
                                                    <a href="https://codeandcore.com/privacy-policy/" target="_blank" style="color: #2C2C2C; text-decoration: underline;">Privacy Policy</a>
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

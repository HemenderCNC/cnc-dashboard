<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Team Member Assigned</title>
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
                                    <img src='https://codeandcore.sirv.com/newsletter/contact-top-banner.png' alt='Code and Core Banner' style='display: block;' />
                                </a>
                            </td>
                        </tr>
                        <tr>
                            <td align="left" bgcolor="#FFFFFF" style="padding: 40px 30px;">
                                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                    <tbody>
                                        <tr>
                                            <td align="center">
                                                <h1 style="margin: 0; font-weight: 600; font-size: 22px; line-height: 28px; color: #1A0726;">
                                                    New Team Member Assigned
                                                </h1>
                                                <p style="margin: 8px 0 0 0; font-size: 14px; color: #5D87FF; font-weight: 500;">
                                                    A new employee has been assigned to report to you
                                                </p>
                                                <br>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td align="left">
                                                <p style="margin: 0 0 16px 0; font-size: 14px; color: #2C2C2C;">
                                                    Dear <strong>{{ $manager->name ?? 'Reporting Manager' }} {{ $manager->last_name ?? '' }}</strong>,
                                                </p>
                                                <p style="margin: 0 0 20px 0; font-size: 14px; color: #2C2C2C; line-height: 22px;">
                                                    We are pleased to inform you that a new team member has joined <strong>Code and Core</strong> and has been placed under your direct supervision and leadership. Below are the employee's details:
                                                </p>

                                                <!-- Details Table -->
                                                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #F8FAFC; border-radius: 8px; padding: 15px 20px; border: 1px solid #E2E8F0;">
                                                    <tbody>
                                                        <!-- Employee Name -->
                                                        <tr>
                                                            <td width="40%" align="left" valign="middle" style="border-bottom: 1px solid #E2E8F0; padding: 10px 0;">
                                                                <p style="margin: 0; font-weight: 600; font-size: 13px; color: #64748B;">Employee Name</p>
                                                            </td>
                                                            <td width="60%" align="left" valign="middle" style="border-bottom: 1px solid #E2E8F0; padding: 10px 0;">
                                                                <p style="margin: 0; font-weight: 600; font-size: 14px; color: #1E293B;">
                                                                    {{ $employee->name ?? '' }} {{ $employee->last_name ?? '' }}
                                                                </p>
                                                            </td>
                                                        </tr>

                                                        <!-- Employee ID -->
                                                        @if(!empty($employee->employee_id))
                                                        <tr>
                                                            <td width="40%" align="left" valign="middle" style="border-bottom: 1px solid #E2E8F0; padding: 10px 0;">
                                                                <p style="margin: 0; font-weight: 600; font-size: 13px; color: #64748B;">Employee ID</p>
                                                            </td>
                                                            <td width="60%" align="left" valign="middle" style="border-bottom: 1px solid #E2E8F0; padding: 10px 0;">
                                                                <p style="margin: 0; font-weight: 500; font-size: 14px; color: #1E293B;">
                                                                    {{ $employee->employee_id }}
                                                                </p>
                                                            </td>
                                                        </tr>
                                                        @endif

                                                        <!-- Company Email -->
                                                        <tr>
                                                            <td width="40%" align="left" valign="middle" style="border-bottom: 1px solid #E2E8F0; padding: 10px 0;">
                                                                <p style="margin: 0; font-weight: 600; font-size: 13px; color: #64748B;">Company Email</p>
                                                            </td>
                                                            <td width="60%" align="left" valign="middle" style="border-bottom: 1px solid #E2E8F0; padding: 10px 0;">
                                                                <p style="margin: 0; font-weight: 500; font-size: 14px; color: #1E293B;">
                                                                    {{ $employee->email ?? '--' }}
                                                                </p>
                                                            </td>
                                                        </tr>

                                                        <!-- Department -->
                                                        @if(!empty($departmentName))
                                                        <tr>
                                                            <td width="40%" align="left" valign="middle" style="border-bottom: 1px solid #E2E8F0; padding: 10px 0;">
                                                                <p style="margin: 0; font-weight: 600; font-size: 13px; color: #64748B;">Department</p>
                                                            </td>
                                                            <td width="60%" align="left" valign="middle" style="border-bottom: 1px solid #E2E8F0; padding: 10px 0;">
                                                                <p style="margin: 0; font-weight: 500; font-size: 14px; color: #1E293B;">
                                                                    {{ $departmentName }}
                                                                </p>
                                                            </td>
                                                        </tr>
                                                        @endif

                                                        <!-- Designation -->
                                                        @if(!empty($designationName))
                                                        <tr>
                                                            <td width="40%" align="left" valign="middle" style="border-bottom: 1px solid #E2E8F0; padding: 10px 0;">
                                                                <p style="margin: 0; font-weight: 600; font-size: 13px; color: #64748B;">Designation</p>
                                                            </td>
                                                            <td width="60%" align="left" valign="middle" style="border-bottom: 1px solid #E2E8F0; padding: 10px 0;">
                                                                <p style="margin: 0; font-weight: 500; font-size: 14px; color: #1E293B;">
                                                                    {{ $designationName }}
                                                                </p>
                                                            </td>
                                                        </tr>
                                                        @endif

                                                        <!-- Joining Date -->
                                                        @if(!empty($employee->joining_date))
                                                        <tr>
                                                            <td width="40%" align="left" valign="middle" style="padding: 10px 0;">
                                                                <p style="margin: 0; font-weight: 600; font-size: 13px; color: #64748B;">Joining Date</p>
                                                            </td>
                                                            <td width="60%" align="left" valign="middle" style="padding: 10px 0;">
                                                                <p style="margin: 0; font-weight: 500; font-size: 14px; color: #1E293B;">
                                                                    {{ date('d-m-Y', strtotime($employee->joining_date)) }}
                                                                </p>
                                                            </td>
                                                        </tr>
                                                        @endif
                                                    </tbody>
                                                </table>

                                                <p style="margin: 24px 0 0 0; font-size: 14px; color: #2C2C2C; line-height: 22px;">
                                                    Kindly connect with them, assist with their onboarding process, and introduce them to the team and upcoming project assignments.
                                                </p>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td align="left" bgcolor="#F6F5FF" style="padding: 40px 70px;">
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
                                        <tr><td height="25"></td></tr>
                                        <tr>
                                            <td align="center">
                                                <a href="https://codeandcore.com/" target="_blank">
                                                    <img src="https://codeandcore.sirv.com/newsletter/cnc.png" alt="Code and Core" width="200" />
                                                </a>
                                            </td>
                                        </tr>
                                        <tr><td height="20"></td></tr>
                                        <tr>
                                            <td align="center">
                                                <p style="margin: 8px 0; font-size: 13px; color: #64748B;">422,423,410 - S.V. Square commercial building, New Ranip, Ahmedabad, Gujarat, India.</p>
                                                <p style="margin: 0; font-size: 13px; color: #64748B;">&copy; {{ date('Y') }} Code and Core Tech LLP</p>
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

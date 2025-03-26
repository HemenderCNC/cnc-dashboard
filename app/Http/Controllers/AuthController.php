<?php

// app/Http/Controllers/AuthController.php

// app/Http/Controllers/AuthController.php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\PersonalAccessToken;
use App\Helpers\MailHelper;
use App\Http\Controllers\TimesheetController;
use App\Models\Timesheet;
use Carbon\Carbon;

class AuthController extends Controller
{
    public function showAdminRegistrationForm()
    {
        // Redirect to the default route if an admin exists
        if (User::where('role', 'admin')->exists()) {
            return redirect()->route('login')->with('error', 'Administrator already exists.');
        }

        // Redirect to dashboard if user is logged in
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('register-admin');
    }

    public function registerAdmin(Request $request)
    {
        // Redirect to the default route if an admin exists

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|confirmed|min:6',
        ]);
        if ($validator->fails()) {

                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422); // 422 Unprocessable Entity status code
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'admin',
        ]);

        // return redirect()->route('login');
        // Create token
        $token = PersonalAccessToken::createToken($user, 'auth_token', ['*'], 6000);

        return response()->json([
            'access_token' => $token,
            'user' => $user,
            'token_type' => 'Bearer',
            'expires_in' => 6000, // Expiration in minutes
        ]);
    }

    public function showLoginForm()
    {
        // Redirect to dashboard if user is already logged in
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        // return view('login');
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => 'Email or password not match.'], 422);
        }

        // Get user by email
        $user = User::where('email', $request->email)->first();

        $role = Role::where('id', $user->role->id)->first();
        $user->role_id = $role->_id;  // Assuming role_id is used to reference the role
        $user->save();
        // Check if the password is correct
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['errors' => 'Email or password not match.'], 422);
        }

        // Generate a custom token
        $token = PersonalAccessToken::createToken($user, 'auth_token', ['*'],6000);

        // Return the token
        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'user' => $user,
        ]);
    }

    public function forgotPassword(Request $request){
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => 'Email address not exist.'], 422);
        }
        $user = User::where('email', $request->email)->first();
        $otp = random_int(100000, 999999);
        $user->otp = $otp;
        $user->otp_expires_at = now()->addMinutes(15); // OTP valid for 15 minutes
        $user->save();
        $isMailSent = MailHelper::sendMail(
            $user->email,
            'Your Password Reset OTP',
            'emails.otp',
            ['otp' => $otp]
        );

        if (!$isMailSent) {
            return response()->json(['message' => 'Failed to send OTP email. Please try again later.'], 500);
        }
        return response()->json([
            'message' => 'OTP has been sent to your email address.',
        ]);
    }

    public function otpVerify(Request $request){
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'otp' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422); // 422 Unprocessable Entity status code
        }
        $user = User::where('email', $request->email)->first();
        // Check if OTP is correct and not expired
        if ($user->otp != $request->otp) {
            return response()->json(['message' => 'Invalid OTP.'], 422);
        }

        if (now()->greaterThan($user->otp_expires_at)) {
            return response()->json(['message' => 'OTP has expired.'], 422);
        }

        // Return success response
        return response()->json([
            'message' => 'OTP verified successfully.',
        ]);
    }

    public function resetPassword(Request $request)
    {
        // Validate the input
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|digits:6',
            'password' => 'required|min:6|confirmed', // Ensure password confirmation
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Find the user
        $user = User::where('email', $request->email)->first();

        // Validate OTP
        if ($user->otp != $request->otp) {
            return response()->json(['message' => 'Invalid OTP.'], 422);
        }

        if (now()->greaterThan($user->otp_expires_at)) {
            return response()->json(['message' => 'OTP has expired.'], 422);
        }

        // Reset the password
        $user->password = Hash::make($request->password);
        $user->otp = null; // Clear the OTP
        $user->otp_expires_at = null;
        $user->save();

        // Return success response
        return response()->json([
            'message' => 'Password reset successfully.',
        ]);
    }

    public function dashboard(Request $request)
    {
        $userData = $request->user;
        //return view('dashboard', ['user' => Auth::user()]);
        return response()->json([
            'userdata' => $userData,
        ]);
    }

    public function logout(Request $request)
    {
        $userID = $request->user->id;
        if($userID){
            $timesheet = Timesheet::where('employee_id', $userID)
            ->where('status', 'running')
            ->first();
            if ($timesheet) {
                $timesheet->status = 'paused';
                $timesheet->save();
                $timesheetController = new TimesheetController();
                $timesheetController->userBreakLogStart($userID);
            }
        }
        return response()->json([
            'message' => 'Logout successfully.',
        ]);
    }
}

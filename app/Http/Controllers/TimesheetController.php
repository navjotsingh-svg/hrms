<?php



namespace App\Http\Controllers;



use Illuminate\Support\Facades\Auth;



class TimesheetController extends Controller

{

    public function index()

    {

        abort_unless(Auth::user()?->canAccessTimesheets(), 403);



        return view('timesheets.index', [

            'canSubmitTimesheets' => Auth::user()->canSubmitTimesheets(),

            'canReviewTeamTimesheets' => Auth::user()->canReviewTeamTimesheets(),

            'ownEmployeeId' => Auth::user()->employee?->id,

        ]);

    }

}



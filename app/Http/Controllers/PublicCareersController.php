<?php



namespace App\Http\Controllers;



use App\Models\Company;

use App\Models\JobPosting;

use App\Services\HiringService;

use Illuminate\Http\RedirectResponse;

use Illuminate\Http\Request;

use Illuminate\View\View;



class PublicCareersController extends Controller

{

    public function __construct(private HiringService $hiringService) {}



    public function show(string $slug): View

    {

        $company = Company::query()->where('slug', $slug)->firstOrFail();



        try {

            $data = $this->hiringService->publicCareersPage($company);

        } catch (\Illuminate\Validation\ValidationException) {

            abort(404, 'Careers page not available.');

        }



        return view('careers.show', $data);

    }



    public function job(string $slug, JobPosting $jobPosting): View

    {

        $company = Company::query()->where('slug', $slug)->firstOrFail();



        if ((int) $jobPosting->company_id !== (int) $company->id || $jobPosting->status !== JobPosting::STATUS_OPEN) {

            abort(404);

        }



        try {

            $data = $this->hiringService->publicCareersPage($company);

        } catch (\Illuminate\Validation\ValidationException) {

            abort(404);

        }



        return view('careers.job', array_merge($data, ['selectedJob' => $jobPosting]));

    }



    public function apply(Request $request, string $slug, JobPosting $jobPosting): RedirectResponse

    {

        $company = Company::query()->where('slug', $slug)->firstOrFail();



        if ((int) $jobPosting->company_id !== (int) $company->id || $jobPosting->status !== JobPosting::STATUS_OPEN) {

            abort(404);

        }



        $validated = $this->validateApplication($request);



        $this->hiringService->applyFromCareers(

            $company,

            $jobPosting,

            $validated,

            $request->file('resume')

        );



        return back()->with('success', 'Your application has been submitted successfully.');

    }



    public function applyGeneral(Request $request, string $slug): RedirectResponse

    {

        $company = Company::query()->where('slug', $slug)->firstOrFail();



        $validated = $this->validateApplication($request, true);



        $job = null;

        if (! empty($validated['job_id'])) {

            $job = JobPosting::query()

                ->where('company_id', $company->id)

                ->where('status', JobPosting::STATUS_OPEN)

                ->findOrFail($validated['job_id']);

        }



        $this->hiringService->applyFromCareers(

            $company,

            $job,

            $validated,

            $request->file('resume')

        );



        return back()->with('success', 'Your application has been submitted successfully.');

    }



    private function validateApplication(Request $request, bool $allowJobSelect = false): array

    {

        $rules = [

            'first_name' => ['required', 'string', 'max:100'],

            'last_name' => ['required', 'string', 'max:100'],

            'email' => ['required', 'email', 'max:255'],

            'phone' => ['nullable', 'string', 'max:30'],

            'resume' => ['required', 'file', 'max:5120', 'mimes:pdf,doc,docx'],

        ];



        if ($allowJobSelect) {

            $rules['job_id'] = ['nullable', 'integer', 'exists:job_postings,id'];

        }



        return $request->validate($rules);

    }

}


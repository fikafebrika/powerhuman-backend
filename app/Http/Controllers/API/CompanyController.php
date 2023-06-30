<?php

namespace App\Http\Controllers\API;

use Exception;
use App\Models\User;
use App\Models\Company;
use App\Models\UserCompany;
use Illuminate\Http\Request;
use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\CreateCompanyRequest;
use App\Http\Requests\UpdateCompanyRequest;

class CompanyController extends Controller
{
    public function fetch(Request $request) {
        $id = $request->input('id');
        $name = $request->input('name');
        $limit = $request->input('limit', 10);

        // Get multiple data
        $companies = Company::with(['user_companies.user'])->whereHas('user_companies', function ($query) {
            $query->where('user_id', Auth::id());
        });

        // Get single data
        if ($id) {
            $company = $companies->find($id);

            if ($company) {
                return ResponseFormatter::success($company, 'Company found');
            }

            return ResponseFormatter::error('Company not found', 404);
        }

        if ($name) {
            $companies->where('name', 'like', '%' . $name . '%');
        }

        return ResponseFormatter::success(
            $companies->paginate($limit),
            'Companies found'
        );
    }

    public function create(CreateCompanyRequest $request) {

        try {

            // Upload logo
            if ($request->hasFile('logo')) {
                $path = $request->file('logo')->store('public/logos');
            }

            // Create company
            $company = Company::create([
                'name' => $request->name,
                'logo' => isset($path) ? $path : '',
            ]);

            if (!$company) {
                throw new Exception('Company not created');
            }

            // Associate user and company to user_companies
            $user = User::find(Auth::id());
            $company = Company::find($company->id);
            $user_companies = new UserCompany();
            $user_companies->user()->associate($user);
            $user_companies->company()->associate($company);
            $user_companies->save();

            // Load users at company
            $company->load(['user_companies.user']);

            return ResponseFormatter::success($company, 'Company created');

        } catch (Exception $e) {
            return ResponseFormatter::error($e->getMessage(), 500);
        }

    }

    public function update(UpdateCompanyRequest $request, $id) {

        try {
            // Get company
            $company = Company::find($id);

            // Check if company exists
            if (!$company) {
                throw new Exception('Company not found');
            }

            // Upload logo
            if ($request->hasFile('logo')) {
                $path = $request->file('logo')->store('public/logos');
            }

            // Update company
            $company->update([
                'name' => $request->name,
                'logo' => isset($path) ? $path : $company->logo,
            ]);

            return ResponseFormatter::success($company, 'Company updated');

        } catch (Exception $e) {
            return ResponseFormatter::error($e->getMessage(), 500);
        }

    }
}

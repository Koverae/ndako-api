<?php


namespace App\Http\Controllers;


use App\Http\Requests\Onboarding\GettingStartedRequest;
use App\Http\Resources\CompanyResource;
use App\Services\OnboardingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Modules\Settings\Models\Currency\Currency;
use Modules\Settings\Models\Language\Language;
use Modules\Settings\Models\Localization\Country;
use App\Support\CityCatalog;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;


class OnboardingController extends Controller
{
    public function __construct(private OnboardingService $service) {

    }


    public function options(Request $request): JsonResponse
    {
        $countryCode = strtoupper($request->query('country_code', 'KE'));

        $types = [
            ['id' => 'hotel', 'label' => __('Hotel')],
            ['id' => 'lodge', 'label' => __('Lodge')],
            ['id' => 'guesthouse-bnb', 'label' => __('Guesthouse / B&B')],
            ['id' => 'hostel', 'label' => __('Hostel')],
            ['id' => 'serviced-apartment', 'label' => __('Serviced Apartment / Aparthotel')],
            ['id' => 'holiday-home', 'label' => __('Holiday Home / Villa')],
        ];


        $roles = [
            ['id' => 'owner', 'label' => __('Owner')],
            ['id' => 'manager', 'label' => __('General / Property Manager')],
            ['id' => 'front-office', 'label' => __('Front Office (Reception & Concierge)')],
            ['id' => 'reservations', 'label' => __('Reservations Agent')],
            ['id' => 'housekeeping', 'label' => __('Housekeeping')],
            ['id' => 'maintenance', 'label' => __('Maintenance Technician')],
            ['id' => 'accounting', 'label' => __('Accountant / Finance')],
            ['id' => 'cashier', 'label' => __('POS Cashier')],
        ];


        $countries = Country::whereIn('country_code', ['KE','UG','TZ','RW'])->get(['id','name','country_code']);
        $currencies = Currency::whereIn('code', ['KES','UGX','TZS','RWF','USD','EUR','GBP','CNY'])->get(['id','name','code']);
        $languages = Language::whereIn('iso_code', ['en'])->get(['id','name','iso_code']);


        return response()->json([
            'types' => $types,
            'roles' => $roles,
            'countries' => $countries,
            'currencies' => $currencies,
            'languages' => $languages,
            'cities' => CityCatalog::for($countryCode),
        ]);
    }


    public function checkWebsite(Request $request): JsonResponse
    {
        $url = $request->query('url');
        abort_unless($url, Response::HTTP_BAD_REQUEST, 'url is required');
        $available = !$this->service->websiteExists($url);
        return response()->json(['available' => $available]);
    }


    public function store(GettingStartedRequest $request): JsonResponse
    {
        $data = $request->validated();
        $company = $this->service->setup($request->user(), $data);
        return response()->json([
            'message' => __('Onboarding completed successfully'),
            'company' => new CompanyResource($company)
        ], 201);
    }

}

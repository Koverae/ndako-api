<?php


namespace App\Http\Requests\Onboarding;


use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;


class GettingStartedRequest extends FormRequest
{

    public function authorize(): bool
    {
        return $this->user() !== null; // must be authenticated
    }


    public function rules(): array
    {
        return [
            'name' => ['required','string','max:120'],
            'type' => ['required','string', Rule::in([
                'hotel','lodge','guesthouse-bnb','hostel','serviced-apartment','holiday-home'
                ])],
            'language' => ['required','integer','exists:languages,id'],
            'currency' => ['required','integer','exists:currencies,id'],
            'rooms' => ['required','integer','min:1'],
            'city' => ['required','string','max:50'],
            'country' => ['required','integer','exists:countries,id'],
            'website' => ['nullable','url','unique:companies,website'],
            'role' => ['required','string', Rule::in([
            'owner','manager','front-office','reservations','housekeeping','maintenance','accounting','cashier'
            ])],
            'billing_cycle' => ['nullable', Rule::in(['monthly','yearly'])],
        ];
    }
}

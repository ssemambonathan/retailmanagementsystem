<?php declare(strict_types=1);
namespace App\Http\Requests\Auth;
use Illuminate\Foundation\Http\FormRequest;
class ForgotPasswordRequest extends FormRequest {
    public function authorize(): bool { return true; }
    public function rules(): array { return ['email'=>['required','email:rfc','max:255']]; }
    public function messages(): array { return ['email.required'=>'Email address is required.']; }
}

<?php declare(strict_types=1);
namespace App\Http\Requests\Auth;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
class RegisterRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'first_name'=>['required','string','max:80','regex:/^[\pL\s\-]+$/u'],
            'last_name' =>['required','string','max:80','regex:/^[\pL\s\-]+$/u'],
            'email'     =>['required','email:rfc,dns','max:255','unique:users,email'],
            'phone'     =>['nullable','string','max:20'],
            'password'  =>['required','confirmed',Password::min(8)->letters()->mixedCase()->numbers()->symbols()->uncompromised(3)],
        ];
    }
}

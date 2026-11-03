<?php declare(strict_types=1);
namespace App\Http\Requests\Auth;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
class ChangePasswordRequest extends FormRequest {
    public function authorize(): bool { return (bool) $this->user(); }
    public function rules(): array {
        return ['current_password'=>['required','string'],'password'=>['required','confirmed','different:current_password',Password::min(8)->letters()->mixedCase()->numbers()->symbols()->uncompromised(3)]];
    }
}

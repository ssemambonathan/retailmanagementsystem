<?php declare(strict_types=1);
namespace App\Http\Requests\Auth;
use Illuminate\Foundation\Http\FormRequest;
class LoginRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return ['email'=>['required','email:rfc','max:255'],'password'=>['required','string'],'device_name'=>['nullable','string','max:100']];
    }
    public function messages(): array { return ['email.required'=>'Credentials are required.','password.required'=>'Credentials are required.']; }
}

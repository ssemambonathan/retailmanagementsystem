<?php declare(strict_types=1);
namespace App\Exceptions;
class EmailNotVerifiedException extends \RuntimeException {
    public function render(): \Illuminate\Http\JsonResponse { return response()->json(['status'=>'error','message'=>$this->getMessage(),'code'=>'EMAIL_NOT_VERIFIED'],403); }
}

<?php declare(strict_types=1);
namespace App\Exceptions;
class MustChangePasswordException extends \RuntimeException {
    public function render(): \Illuminate\Http\JsonResponse { return response()->json(['status'=>'error','message'=>$this->getMessage(),'code'=>'MUST_CHANGE_PASSWORD'],403); }
}

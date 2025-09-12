<?php
// app/Http/Requests/CommentStoreRequest.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CommentStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array {
        return [
            'body' => ['required','string'],
            'attachments.*' => ['file','max:10240'], // 10MB c/u
        ];
    }
}

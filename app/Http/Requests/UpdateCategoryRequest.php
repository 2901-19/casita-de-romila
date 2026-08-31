<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class UpdateCategoryRequest extends FormRequest {
    public function authorize(): bool { return true; }
    public function rules(): array {
        return ['name' => ['required', 'string', 'max:100', Rule::unique('categories')->ignore($this->route('category'))]];
    }
}

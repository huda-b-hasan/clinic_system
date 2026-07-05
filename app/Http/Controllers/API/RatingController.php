<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Rating;
use App\Models\Treatment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RatingController extends Controller
{
    public function index()
    {

        $ratings = Rating::with([
            'user:id,name,email',
            'treatment:id,name',
        ])->latest()->get();

        // حساب إجمالي التقييمات بالسيستم وإيجاد المتوسط العام (اختياري، كإحصائية عامة للآدمن)
        $totalRatings = $ratings->count();
        $overallAverage = Rating::avg('stars_number');

        return response()->json([
            'message' => 'تم جلب جميع التقييمات بنجاح',
            'overall_average' => $overallAverage ? round($overallAverage, 1) : 0,
            'total_ratings' => $totalRatings,
            'data' => $ratings,
        ], 200);
    }
    
    public function getTreatmentRatings($treatment_id)
    {
        $treatment = Treatment::find($treatment_id);
        if (! $treatment) {
            return response()->json(['message' => 'الخدمة التجميلية غير موجودة'], 404);
        }

        $ratings = Rating::where('treatment_id', $treatment_id)
            ->with('user:id,name,email')
            ->latest()
            ->get();

        $averageRating = Rating::where('treatment_id', $treatment_id)->avg('stars_number');

        return response()->json([
            'treatment_name' => $treatment->name,
            'average_rating' => $averageRating ? round($averageRating, 1) : 0,
            'total_reviews' => $ratings->count(),
            'ratings' => $ratings,
        ], 200);
    }

    /**
     * إضافة تقييم جديد لخدمة تجميلية
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'treatment_id' => 'required|exists:treatments,id', // التأكد أن الخدمة موجودة بقاعدة البيانات
            'stars_number' => 'required|integer|min:1|max:5',  // النجوم يجب أن تكون بين 1 و 5
            'comment' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // جوة الـ store() في RatingController عدلي السطر ده:
        $userId = session('user_id') ?? $request->user_id;

        if (! $userId) {
            return response()->json(['message' => 'يجب تسجيل الدخول لإضافة تقييم'], 401);
        }

        // منع المستخدم من تكرار تقييم الخدمة نفسها (اختياري - حسب رغبتكِ بالسيستم)
        $alreadyRated = Rating::where('user_id', $userId)
            ->where('treatment_id', $request->treatment_id)
            ->exists();

        if ($alreadyRated) {
            return response()->json(['message' => 'لقد قمتِ بتقييم هذه الخدمة مسبقاً!'], 400);
        }

        // إنشاء التقييم
        $rating = Rating::create([
            'user_id' => $userId,
            'treatment_id' => $request->treatment_id,
            'stars_number' => $request->stars_number,
            'comment' => $request->comment ?? '',        ]);

        return response()->json([
            'message' => 'تم إضافة تقييمكِ بنجاح، شكراً لكِ!',
            'data' => $rating,
        ], 201);
    }

    /**
     * تعديل تقييم سابق (مثلاً تعديل النجوم أو التعليق)
     */
    public function update(Request $request, $id)
    {
        $rating = Rating::find($id);

        if (! $rating) {
            return response()->json(['message' => 'التقييم غير موجود'], 404);
        }

        // التحقق من صلاحية التعديل (فقط صاحب التقييم يعدله)
        // جوة الـ store() في RatingController عدلي السطر ده:
        $userId = session('user_id') ?? $request->user_id;

        if (! $userId) {
            return response()->json(['message' => 'يجب تسجيل الدخول لإضافة تقييم'], 401);
        }

        $validator = Validator::make($request->all(), [
            'stars_number' => 'sometimes|required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $rating->update($request->only(['stars_number', 'comment']));

        return response()->json([
            'message' => 'تم تحديث التقييم بنجاح',
            'data' => $rating,
        ], 200);
    }

    /**
     * حذف تقييم
     */
    public function destroy(Request $request, $id)
    {
        $rating = Rating::find($id);

        if (! $rating) {
            return response()->json(['message' => 'التقييم غير موجود'], 404);
        }

        // التأكد من الصلاحية قبل الحذف
        $userId = auth()->id() ?? $request->user_id;
        if ($rating->user_id != $userId) {
            return response()->json(['message' => 'لا تملكين صلاحية حذف هذا التقييم'], 403);
        }

        $rating->delete();

        return response()->json(['message' => 'تم حذف التقييم بنجاح'], 200);
    }

}

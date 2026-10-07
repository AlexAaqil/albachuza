<?php

namespace Modules\Product\Exceptions;

use Exception;

class InsufficientStockException extends Exception
{
    /**
     * Render the exception as an HTTP response.
     */
    public function render($request)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => $this->getMessage(),
                'error' => 'insufficient_stock',
                'code' => 422
            ], 422);
        }
        
        // For Inertia.js requests
        if ($request->inertia()) {
            return back()->with('error', $this->getMessage());
        }
        
        return redirect()->back()
            ->with('error', $this->getMessage())
            ->withInput();
    }
}

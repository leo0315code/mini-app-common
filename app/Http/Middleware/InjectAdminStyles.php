<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InjectAdminStyles
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $response->isOk() || ! $response->headers->has('content-type') || ! str_contains($response->headers->get('content-type'), 'text/html')) {
            return $response;
        }

        $html = $response->getContent();

        $styleTag = '<style id="admin-compact-styles">
/* 直接覆盖所有表格列组件的 padding-block */
.fi-ta-color,.fi-ta-color:not(.fi-inline),
.fi-ta-checkbox,
.fi-ta-icon,
.fi-ta-image,
.fi-ta-selection-cell,
.fi-ta-summary-row-heading-cell,
.fi-ta-summary-header-cell,
.fi-ta-individual-search-cell,
.fi-ta-header-cell,
.fi-ta-cell,
.fi-ta-actions,
.fi-ta-select{padding-block:.375rem!important;padding-inline:.5rem!important}
.fi-ta-color>.fi-ta-color-item{width:1.25rem!important;height:1.25rem!important}
.fi-ta-header-toolbar-ctn{padding:.25rem.5rem!important;gap:.5rem!important}
</style>';

        // 注入到 <body> 内部 — Livewire 只更新 body，不更新 head
        if (str_contains($html, 'admin-compact-styles')) {
            return $response;
        }

        // 用 strpos 找 body 标签结束位置
        $bodyPos = strpos($html, '<body');
        if ($bodyPos === false) {
            return $response;
        }

        // 找 body 标签的 > 结束符
        $gtPos = strpos($html, '>', $bodyPos);
        if ($gtPos === false) {
            return $response;
        }

        // 在 body 标签结束后注入
        $html = substr_replace($html, $styleTag . "\n", $gtPos + 1, 0);
        $response->setContent($html);

        return $response;
    }
}

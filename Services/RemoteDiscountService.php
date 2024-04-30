<?php

namespace Plugin\RemoteDiscount\Services;

use Beike\Shop\Services\CheckoutService;

class RemoteDiscountService
{
    /**
     * @param CheckoutService $checkout
     * @return array
     */
    public static function getTotal(CheckoutService $checkout)
    {
        $callbackUrl = plugin_setting('remote_discount.remote_addr');
        /** @var \Beike\Shop\Services\TotalService $totalService */
        $totalService = $checkout->totalService;
        $products = $totalService->getCartProducts();
        \Log::info('RemoteDiscountService CartProducts', $products);
        /** @var \Beike\Models\Customer $customer */
        $customer     = current_customer();
        \Log::info('RemoteDiscountService customer', [$customer->toArray()]);
        if (empty($customer)) {
            return null;
        }
        // 业务平台检查没有vip的spu就返回优惠0
        $result = self::postData($callbackUrl, [
            'customer' => $customer->toArray(),
            'products' => $products,
        ]);

        if (empty($result) || $result['code'] !== 0 || empty($result['amount'])) {
            return null;
        }

        $amount       = $result['amount'];

        $totalData    = [
            'code'          => 'lock_vip_discount',
            'title'         => trans('RemoteDiscount::common.lock_vip_discount'),
            'amount'        => -$amount,
            'amount_format' => currency_format(-$amount),
        ];

        $totalService->amount += $totalData['amount'];
        if ($totalService->amount < 0.01) {
            $totalService->amount = 0.01;
        }
        $totalService->totals[] = $totalData;
    }

    protected static function postData($callbackUrl, $data)
    {
        try{
            $response = (new \GuzzleHttp\Client)->post($callbackUrl, [
                'json' => $data,
                'timeout' => 3,
            ]);
            // 判断状态是否200
            if ($response->getStatusCode() !== 200) {
                throw new \Exception('响应状态码' . $response->getStatusCode());
            }
            // 判断是否json
            $content = $response->getBody()->getContents();
            if (!str_contains($response->getHeaderLine('Content-Type'), 'application/json')) {
                throw new \Exception('响应类型不是json：' . $content);
            }
            // 判断json的code是否是0
            $result = json_decode($content, true);
            if ($result['code'] !== 0) {
                throw new \Exception('响应code不是0：' . $content);
            }
        } catch (\Exception $exception) {
            app('log')->info('RemoteDiscount插件: 请求回调地址异常', [$callbackUrl, $data, $exception->getMessage()]);
            return null;
        }

        app('log')->info('RemoteDiscount插件: 回调结果', [$callbackUrl, $data, $result]);
        return $result;
    }
}

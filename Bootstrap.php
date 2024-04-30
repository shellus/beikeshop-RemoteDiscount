<?php
namespace Plugin\RemoteDiscount;


class Bootstrap
{
    public function boot()
    {
        $this->beforeOrderPay();
    }

    public function beforeOrderPay()
    {
        add_hook_filter('service.total.maps', function ($maps) {
            $maps['remote_discount'] = "\Plugin\RemoteDiscount\Services\RemoteDiscountService";
            return $maps;
        });
    }

}

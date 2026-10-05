<x-admin::form.control-group.controls.inline.address
    ::name="'{{ $attribute->code }}'"
    :value="$value"
    :rules="\Webkul\Admin\Support\OptionalSalesAddress::appliesTo($attribute->entity_type) ? '' : 'required'"
    position="left"
    :label="$attribute->name"
    ::errors="errors"
    :placeholder="$attribute->name"
    :url="$url"
    :allow-edit="$allowEdit"
/>

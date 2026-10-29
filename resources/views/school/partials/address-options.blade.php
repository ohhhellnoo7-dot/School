<div class="d-flex flex-wrap align-items-center gap-2 mb-2" data-address-controls="{{ $addressId }}">
    <label class="visually-hidden" for="{{ $addressId }}-mode">Address entry option</label>
    <select id="{{ $addressId }}-mode" class="form-select w-auto" data-address-mode aria-label="Address entry option">
        <option value="manual">Enter my own address</option>
        <option value="random">Random sample in Phnom Penh</option>
    </select>
    <button type="button" class="btn btn-outline-secondary" data-address-random hidden>Generate another</button>
    <small class="text-secondary w-100" data-address-note hidden>Generated sample address in Phnom Penh, Cambodia. You can edit it before saving.</small>
</div>

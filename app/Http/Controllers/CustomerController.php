<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index()
    {
        // $customers = Customer::all();
        $customers = Customer::orderBy('cid')->paginate(5);
        return view('customers.index', compact('customers'));
    }

    public function create()
    {
        return view('customers.create');
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required']);
        Customer::create($request->all());
        return redirect()->route('customers.index')->with('success', 'Customer added.');
    }

    public function show(int $id)
    {
        $customer = Customer::findOrFail($id);
        return view('customers.show', compact('customer'));
    }

    public function edit(int $id)
    {
        $customer = Customer::findOrFail($id);
        return view('customers.edit', compact('customer'));
    }

    public function update(Request $request, int $id)
    {
        $customer = Customer::findOrFail($id);
        $request->validate(['name' => 'required']);
        $customer->update($request->all());
        return redirect()->route('customers.index')->with('success', 'Customer updated.');
    }

    public function destroy ($id)
    {
        $student = Customer::findOrFail($id);
        $student->delete();
        return redirect()->route('customers.index')->with('success','Customer deleted.');
    
    }
    
}

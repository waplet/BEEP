<div class="col-xs-12">
	<div class="form-group {{ $errors->has('name') ? 'has-error' : ''}}">
	    <label for="name" control-label>{{ 'Name' }}</label>
	    <div>
	        <input class="form-control" name="name" type="text" id="name" value="{{ $sensordefinition->name or ''}}" >
	        {!! $errors->first('name', '<p class="help-block">:message</p>') !!}
	    </div>
	</div>
</div>
<div class="col-xs-12">
	<div class="form-group {{ $errors->has('inside') ? 'has-error' : ''}}">
	    <label for="inside" control-label>{{ 'Inside' }}</label>
	    <div>
	        <input class="form-control" name="inside" type="number" min="0" max="1" id="inside" value="{{ $sensordefinition->inside or ''}}" >
	        {!! $errors->first('inside', '<p class="help-block">:message</p>') !!}
	    </div>
	</div>
</div>
<div class="col-xs-12">
	<div class="form-group {{ $errors->has('offset') ? 'has-error' : ''}}">
	    <label for="offset" control-label>{{ 'Offset (zero Value)' }}</label>
	    <div>
	        <input class="form-control" name="offset" type="text" id="offset" value="{{ $sensordefinition->offset or ''}}" >
	        {!! $errors->first('offset', '<p class="help-block">:message</p>') !!}
	    </div>
	</div>
</div>
<div class="col-xs-12">
	<div class="form-group {{ $errors->has('multiplier') ? 'has-error' : ''}}">
	    <label for="multiplier" control-label>{{ 'Unit Per Value' }}</label>
	    <div>
	        <input class="form-control" name="multiplier" type="text" id="multiplier" value="{{ $sensordefinition->multiplier or ''}}" >
	        {!! $errors->first('multiplier', '<p class="help-block">:message</p>') !!}
	    </div>
	</div>
</div>

<div class="col-xs-12">
	<div class="form-group {{ $errors->has('measurement_id') ? 'has-error' : ''}}">
	    <label for="measurement_id" control-label>{{ 'Measurement' }}</label>
	    <div>
	        {!! Form::select('measurement_id', App\Measurement::selectList(), isset($sensordefinition->measurement_id) ? $sensordefinition->measurement_id : null, array('id'=>'measurement_id', 'class' => 'form-control select2', 'placeholder'=>'Select physical quantity...')) !!}
	        {!! $errors->first('measurement_id', '<p class="help-block">:message</p>') !!}
	    </div>
	</div>
</div>

<div class="col-xs-12">
	<div class="form-group {{ $errors->has('physical_quantity_id') ? 'has-error' : ''}}">
	    <label for="physical_quantity_id" control-label>{{ 'Physical quantity' }}</label>
	    <div>
	        {!! Form::select('physical_quantity_id', App\PhysicalQuantity::selectList(), isset($sensordefinition->physical_quantity_id) ? $sensordefinition->physical_quantity_id : null, array('id'=>'physical_quantity_id', 'class' => 'form-control select2', 'placeholder'=>'Select physical quantity...')) !!}
	        {!! $errors->first('physical_quantity_id', '<p class="help-block">:message</p>') !!}
	    </div>
	</div>
</div>

<div class="col-xs-12">
	<div class="form-group {{ $errors->has('sensor_id') ? 'has-error' : ''}}">
	    <label for="sensor_id" control-label>{{ 'Sensor' }}*</label>
	    <div>
	        {!! Form::select('sensor_id', Auth::user()->sensors->sortBy('name')->pluck('name','id'), isset($sensordefinition->sensor_id) ? $sensordefinition->sensor_id : null, array('id'=>'sensor_id', 'class' => 'form-control select2', 'placeholder'=>'Select sensor...')) !!}
	        {!! $errors->first('sensor_id', '<p class="help-block">:message</p>') !!}
	    </div>
	</div>
</div>


<div class="col-xs-12" style="margin-top: 20px;">
	<div class="form-group">
    	<input class="btn btn-primary btn-block" type="submit" value="{{ $submitButtonText or 'Create' }}">
    </div>
</div>
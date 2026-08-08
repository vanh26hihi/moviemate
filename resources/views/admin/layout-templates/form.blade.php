@php
    $initialCells = old('layout.cells', $template->exists ? $template->cells->map(fn($c) => $c->only(['x_position','y_position','cell_type','seat_type','seat_label','pair_key']))->values()->all() : []);
    $initial = ['rows' => (int) old('layout.rows', $template->rows ?: 8), 'columns' => (int) old('layout.columns', $template->columns ?: 12), 'screen_position' => old('layout.screen_position', $template->screen_position ?: 'top'), 'cells' => $initialCells];
@endphp
<form method="POST" action="{{ $action }}" id="template-form" class="space-y-6">@csrf @if($method !== 'POST') @method($method) @endif
    <div class="app-card p-5 grid md:grid-cols-2 gap-4">
        <label>Mã mẫu<input class="form-input w-full" name="code" value="{{ old('code', $template->code) }}" required placeholder="STANDARD_100"></label>
        <label>Tên mẫu<input class="form-input w-full" name="name" value="{{ old('name', $template->name) }}" required></label>
        <label>Loại phòng<select class="form-input w-full" name="room_type"><option value="">Mọi loại</option>@foreach(['2D','3D','IMAX','4DX'] as $type)<option @selected(old('room_type',$template->room_type)===$type)>{{ $type }}</option>@endforeach</select></label>
        <label>Mô tả<input class="form-input w-full" name="description" value="{{ old('description', $template->description) }}"></label>
    </div>
    <div class="app-card p-5 space-y-4">
        <div class="flex flex-wrap gap-3 items-end"><label>Hàng<input id="rows" type="number" min="1" max="30" class="form-input w-20" value="{{ $initial['rows'] }}"></label><label>Cột<input id="columns" type="number" min="1" max="40" class="form-input w-20" value="{{ $initial['columns'] }}"></label><label>Màn hình<select id="screen" class="form-input"><option value="top">Phía trên</option><option value="bottom" @selected($initial['screen_position']==='bottom')>Phía dưới</option></select></label>
            <div class="flex gap-2" id="tools">@foreach(['normal'=>'Ghế thường','vip'=>'VIP','couple'=>'Ghế đôi','aisle'=>'Lối đi','empty'=>'Xóa ô'] as $key=>$label)<button type="button" data-tool="{{ $key }}" class="px-3 py-2 rounded border app-border">{{ $label }}</button>@endforeach</div>
        </div>
        <p class="app-muted text-sm">Chọn công cụ rồi bấm vào ô. Nhãn ghế được tạo theo hàng; ghế đôi chiếm hai ô liền kề.</p>
        <div class="text-center py-2 rounded bg-slate-700">MÀN HÌNH</div><div id="grid" class="overflow-auto"></div>
    </div>
    <input type="hidden" name="layout" id="layout-json"><div class="flex gap-3"><button class="btn-primary">Lưu mẫu</button><a class="btn-secondary" href="{{ route('admin.layout-templates.index') }}">Hủy</a></div>
</form>
@if($errors->any())<div class="mt-4 text-red-400">{{ $errors->first() }}</div>@endif
<script>
(() => {
 const seed=@json($initial), cells=new Map(seed.cells.map(c=>[`${c.x_position}:${c.y_position}`,c])); let tool='normal';
 const rowLabel=y=>{let s=''; while(y){y--;s=String.fromCharCode(65+y%26)+s;y=Math.floor(y/26)} return s};
 const render=()=>{const rows=+document.querySelector('#rows').value, cols=+document.querySelector('#columns').value, grid=document.querySelector('#grid'); grid.style.display='grid';grid.style.gridTemplateColumns=`repeat(${cols},2.8rem)`;grid.style.gap='.25rem';grid.innerHTML='';
  for(let y=1;y<=rows;y++)for(let x=1;x<=cols;x++){const k=`${x}:${y}`,c=cells.get(k),b=document.createElement('button');b.type='button';b.className='h-11 rounded border app-border text-xs '+(c?.cell_type==='aisle'?'bg-slate-700':c?.seat_type==='vip'?'bg-amber-600':c?.seat_type==='couple'?'bg-pink-600':c?'bg-indigo-600':'bg-transparent');b.textContent=c?.cell_type==='aisle'?'—':c?.seat_label||'·';b.onclick=()=>paint(x,y,rows,cols);grid.appendChild(b)} sync()};
 const nextLabel=(y,x)=>rowLabel(y)+x;
 const paint=(x,y,rows,cols)=>{const k=`${x}:${y}`; if(tool==='empty'){cells.delete(k)}else if(tool==='aisle'){cells.set(k,{x_position:x,y_position:y,cell_type:'aisle'})}else if(tool==='couple'){if(x>=cols)return;const key=`PAIR-${y}-${x}`;cells.set(k,{x_position:x,y_position:y,cell_type:'seat',seat_type:'couple',seat_label:nextLabel(y,x),pair_key:key});cells.set(`${x+1}:${y}`,{x_position:x+1,y_position:y,cell_type:'seat',seat_type:'couple',seat_label:nextLabel(y,x+1),pair_key:key})}else cells.set(k,{x_position:x,y_position:y,cell_type:'seat',seat_type:tool,seat_label:nextLabel(y,x)});render()};
 const sync=()=>document.querySelector('#layout-json').value=JSON.stringify({rows:+document.querySelector('#rows').value,columns:+document.querySelector('#columns').value,screen_position:document.querySelector('#screen').value,cells:[...cells.values()]});
 document.querySelectorAll('[data-tool]').forEach(b=>b.onclick=()=>{tool=b.dataset.tool;document.querySelectorAll('[data-tool]').forEach(x=>x.classList.remove('ring-2'));b.classList.add('ring-2')});['rows','columns','screen'].forEach(id=>document.querySelector('#'+id).addEventListener('change',render));document.querySelector('#template-form').addEventListener('submit',sync);render();
})();
</script>

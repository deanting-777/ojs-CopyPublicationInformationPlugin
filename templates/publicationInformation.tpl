{**
 * plugins/generic/copyPublicationInformation/block.tpl
 *
 * Copyright (c) 2026 Dean Ting
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Article page sidebar menu -- "Copy Publication Information" block.
 *}
{if isset($publicationInfomation) && empty($publicationInfomation)==false}
	<div class="item copyPublicationInformation">

		<h2 class="label">
			{translate key="plugins.generic.copyPublicationInformation.label"}
		</h2>

		<div class="value">
			<a class="obj_galley_link" href="javascript:void(0);" onclick="set_copy(this);">{translate key="plugins.generic.copyPublicationInformation.button.label"}</a>
			<script>
				function set_copy(_obj){
					let _text={$publicationInfomation nofilter};

					try{
						navigator.clipboard.writeText(_text);
						$(_obj).text('{translate key="plugins.generic.copyPublicationInformation.success"}');
						setTimeout(function(){
							$(_obj).text('{translate key="plugins.generic.copyPublicationInformation.button.label"}');
						},2500);
					}
					catch (err) {
						const _textarea=document.createElement('textarea');
						_textarea.value=_text;
						_textarea.style.position='absolute';
						_textarea.style.left='-9999px';
						document.body.appendChild(_textarea);
						_textarea.select();

						document.execCommand('copy');
						document.body.removeChild(_textarea);

						$(_obj).text('{translate key="plugins.generic.copyPublicationInformation.success"}');
						setTimeout(()=>{
							$(_obj).text('{translate key="plugins.generic.copyPublicationInformation.button.label"}');
						},2500);
					}
				}
			</script>
		</div>
	</div>
{/if}

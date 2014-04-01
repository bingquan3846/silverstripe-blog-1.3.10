<div class="blogSummary">


    <a href="$Link" title="<% _t('VIEWFULL', 'View full post titled -') %> '$Title'">$cover</a>

	<span class="authorDate"> $Date.Long</span>

    <h2 class="postTitle"><a href="$Link" title="<% _t('VIEWFULL', 'View full post titled -') %> '$Title'">$MenuTitle</a></h2>
	<% if BlogHolder.ShowFullEntry %>
		$Content
	<% else %> 
		<div class="overview">$Content.FirstParagraph...<a href="$Link" class="readmore" title="Read Full Post">weiterlesen</a></div>
	<% end_if %>

     <% if TagsCollection %>
    		<div class="tags">
    			Labels:
    			<% control TagsCollection %>
    				<a href="$Link" title="View all posts tagged '$Tag'" rel="tag">$Tag</a>
    			<% end_control %>
    		</div>
      <% end_if %>
</div>

<p class="tagcloud">
	<% control TagsCollection %>
		<a href="$Link" class="$Class">$Tag</a> <% if Last %><% else %>,<% end_if %>
	<% end_control %>
</p>
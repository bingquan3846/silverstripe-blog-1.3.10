<div id="blog" class="blogcontent typography">
	

	<div class="header"><% include BreadCrumbs %></div>
	
	<% if SelectedTag %>
		<h3><% _t('VIEWINGTAGGED', 'Anzeige der Einträge mit Tag') %> '$SelectedTag'</h3>
	<% else_if SelectedDate %>
		<h3><% _t('VIEWINGPOSTEDIN', 'Anzeige der Einträge in') %> $SelectedNiceDate</h3>
	<% else_if SelectedAuthor %>
		<h3><% _t('VIEWINGPOSTEDBY', 'Viewing entries posted by') %> $SelectedAuthor</h3>
	<% end_if %>
	<div class="blogmenu">
	 <div class="label">
      <span>LABELS</span>
     </div>
      <div class="tags">
            <% if TagsCollection %>
                <% control TagsCollection %>
                       <a href="$Link" class="$Class">$Tag</a>
                <% end_control %>
           <% end_if %>
	  </div>

	</div>
	<div class="clear"></div>
	<div class="blogmenu">
	 <div class="archive">
      <span>BLOGARCHIV</span>
     </div>
      <div class="month">
             <% if archiveBlog %>
            <% control archiveBlog %>
            <a href="$Link">$Date.Format(F) $Date.Year<span>&nbsp;($Count)</span></a> <% end_control %>
            <% end_if %>
	  </div>



	</div>
	<div class="clear"></div>

	<% if BlogEntries %>
		<% control BlogEntries %>
			<% include BlogSummary %>
		<% end_control %>
		         <div class="pagination clear">
                <% control BlogEntries.Pages %>
                    <% if CurrentBool %>
                        <div class="silvercart-pagination-marker">
                            <div class="silvercart-pagination-marker_content">
                                <strong>
                                    <span>
                                        $PageNum
                                    </span>
                                </strong>
                            </div>
                        </div>
                    <% else %>
                        <div class="silvercart-pagination-link">
                            <div class="silvercart-pagination-link_content">
                                <a href="$Link" title="<% sprintf(_t('SilvercartPage.GOTO_PAGE', 'go to page %s'),$PageNum) %>">
                                    <span>
                                        $PageNum
                                    </span>
                                </a>
                            </div>
                        </div>
                    <% end_if %>
                <% end_control %>
                </div>
	<% else %>
		<h2><% _t('NOENTRIES', 'There are no blog entries') %></h2>
	<% end_if %>
	
</div>

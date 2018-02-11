<div>
	<h4>Profiling information</h4>
	<div>
		<% if not $Results %>
			<p>No results</p>
		<% else %>
			<% loop $Results %>
				<div>
					<p>
						{$Pos}. {$Class}::{$Function}({$Line}): {$Time}ms (Count: {$Records.Count})
					</p>
					<% if $Records %>
						<ul>
							<% loop $Records %>
								<li>
									<% if $Constructor %>
										<% with $Constructor %>
											Constructed at: {$Class}::{$Function}({$Line})<br/>
										<% end_with %>
									<% end_if %>
									<% with $Caller %>
										Executed at: {$Class}::{$Function}({$Line}): {$Time}ms
									<% end_with %>
								</li>
							<% end_loop %>
						</ul>
					<% end_if %>
				</div>
			<% end_loop %>
		<% end_if %>
	</div>
</div>

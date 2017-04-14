<div>
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
</div>
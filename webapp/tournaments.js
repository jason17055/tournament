var numPlayers = 12;
var numRounds = 3;
var tableSize = 4;

function TournamentPairing()
{
	this.numPlayers = numPlayers;
	this.numRounds = numRounds;
	this.tableSize = tableSize;
}

TournamentPairing.prototype.clone = function()
{
	var newMe = new TournamentPairing();
	newMe.numPlayers = this.numPlayers;
	newMe.numRounds = this.numRounds;
	newMe.tableSize = this.tableSize;
	newMe.rounds = [];
	for (var i = 0; i < this.numRounds; i++)
	{
		var R = [];
		for (var j = 0; j < this.numPlayers; j++)
		{
			R[j] = this.rounds[i][j];
		}
		newMe.rounds.push(R);
	}
	return newMe;
};

TournamentPairing.prototype.mutate = function()
{
	var r = Math.floor(Math.random() * (this.numRounds-1))+1;
	var x = Math.floor(Math.random() * (this.numPlayers));
	var y = Math.floor(Math.random() * (this.numPlayers-1));
	if (y >= x) { y++; }

	// swap two players seats
	var t = this.rounds[r][x];
	this.rounds[r][x] = this.rounds[r][y];
	this.rounds[r][y] = t;
};

TournamentPairing.prototype.initializePairings = function()
{
	this.rounds = [];
	var R1 = [];
	for (var i = 0; i < numPlayers; i++)
	{
		R1.push(String.fromCharCode(65+i));
	}
	this.rounds.push(R1);

	for (var i = 1; i < this.numRounds; i++)
	{
		var Rx = [];
		for (var j = 0; j < this.numPlayers; j++)
		{
			Rx.push(R1[j]);
		}
		// now shuffle the list
		for (var j = 0; j < Rx.length; j++)
		{
			var k = Math.floor(Math.random() * (Rx.length-j) + j);
			var t = Rx[j];
			Rx[j] = Rx[k];
			Rx[k] = t;
		}
		this.rounds.push(Rx);
	}
};

TournamentPairing.prototype.calculatePlayerFitness = function(playerNum)
{
	var playerName = this.rounds[0][+playerNum];

	var seen = {};
	for (var r = 0; r < this.numRounds; r++)
	{
		var R = this.rounds[r];
		var tableId = findTableWithPlayer(R, playerName);
		var opp = [];
		for (var j = tableId*this.tableSize; j < (tableId+1)*this.tableSize; j++)
		{
			if (R[j] != playerName)
			{
				var oppName = R[j];
				opp.push(oppName);
				seen[oppName] = (seen[oppName]||0)+1;
			}
		}
	}

	var numRepeats = 0;
	var numDoubleRepeats = 0;
	for (var oppName in seen)
	{
		if (seen[oppName] >= 3)
			numDoubleRepeats++;
		else if (seen[oppName] >= 2)
			numRepeats++;
	}

	var thisScore = -(numRepeats + 4 * numDoubleRepeats);
	return thisScore;
};

TournamentPairing.prototype.calculateOverallFitness = function()
{
	var sumFitnessSq = 0;
	for (var i = 0; i < this.numPlayers; i++)
	{
		var f = this.calculatePlayerFitness(i);
		sumFitnessSq += (f * f);
	}
	return Math.sqrt(sumFitnessSq);
};

function findTableWithPlayer(R, playerId)
{
	for (var i = 0; i < R.length; i++)
	{
		if (R[i] == playerId)
		{
			return Math.floor(i / tableSize);
		}
	}
	return 0;
}

var myP;
function reportPairings()
{
	var oldFitness = $('#overallFitness').text();
	oldFitness = oldFitness ? (+oldFitness) : Infinity;

	var P = new TournamentPairing();
	P.initializePairings();
	var newFitness = P.calculateOverallFitness();

	if (newFitness >= oldFitness)
		return;

	myP = P;
	$('#overallFitness').text(newFitness);
	$('#pairingsGoHere').empty();

	var $t = $('<table border="1"></table>');
	var $h = $('<tr></tr>');
	for (var i = 0; i < P.numRounds; i++)
	{
		var $td = $('<th></th>');
		$td.text('R'+(i+1));
		$h.append($td);
	}
	$t.append($h);

	var R1 = P.rounds[0];

	var numTables = Math.ceil(P.numPlayers / P.tableSize);
	for (var i = 0; i < numTables; i++)
	{
		var $tr = $('<tr></tr>');
		for (var r = 0; r < P.numRounds; r++)
		{
			var $td = $('<td></td>');
			for (var j = i * P.tableSize; j < (i+1)*P.tableSize; j++)
			{
				var $d = $('<div></div>');
				$d.text(P.rounds[r][j]);
				$td.append($d);
			}
			$tr.append($td);
		}
		$t.append($tr);
	}
	$('#pairingsGoHere').append($t);

	var $t = $('<table border="1"><tr><th>Player</th></tr></table>');
	for (var i = 0; i < P.numRounds; i++)
	{
		var $td = $('<th></th>');
		$td.text('R'+(i+1));
		$('tr',$t).append($td);
	}
	$('tr',$t).append('<th>Fitness</th>');

	var sumFitnessSq = 0;
	for (var i = 0; i < P.numPlayers; i++)
	{
		var $tr = $('<tr></tr>');
		var $td = $('<td></td>');
		$td.text(R1[i]);
		$tr.append($td);

		for (var r = 0; r < P.numRounds; r++)
		{
			var R = P.rounds[r];
			var $td = $('<td></td>');
			var tableId = findTableWithPlayer(R, R1[i]);
			var opp = [];
			for (var j = tableId*P.tableSize; j < (tableId+1)*P.tableSize; j++)
			{
				if (R[j] != R1[i])
				{
					var oppName = R[j];
					opp.push(oppName);
				}
			}
			$td.text(opp.join(', '));
			$tr.append($td);
		}

		var thisScore = P.calculatePlayerFitness(i);
		var $td = $('<td></td>');
		$td.text(thisScore);
		$tr.append($td);

		$t.append($tr);
		sumFitnessSq += (thisScore * thisScore);
	}

	$('#pairingsGoHere').append($t);
}

function doMutate()
{
	var P = myP.clone();
	P.mutate();
}

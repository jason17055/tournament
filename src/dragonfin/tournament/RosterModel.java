package dragonfin.tournament;

import java.util.*;
import javax.swing.table.*;

public class RosterModel extends AbstractTableModel
{
	Tournament tournament;
	static ResourceBundle strings = MainWindow.strings;

	public RosterModel(Tournament tournament)
	{
		this.tournament = tournament;
	}

	@Override
	public String getColumnName(int col) {
		if (col == 0) {
			return strings.getString("roster.name_header");
		}
		else {
			return null;
		}
	}

	@Override
	public int getRowCount()
	{
		return tournament.players.size() + 1;
	}

	@Override
	public int getColumnCount()
	{
		return 1;
	}

	@Override
	public boolean isCellEditable(int row, int col)
	{
		return true;
	}

	@Override
	public Object getValueAt(int row, int col)
	{
		if (row < tournament.players.size()) {
			Player p = tournament.players.get(row);
			return p.getName();
		}
		else {
			return null;
		}
	}

	@Override
	public void setValueAt(Object value, int row, int col)
	{
		Player p;
		if (row < tournament.players.size()) {
			p = tournament.players.get(row);
		}
		else {
			p = new Player(tournament);
			tournament.players.add(p);
			fireTableRowsInserted(
				tournament.players.size(),
				tournament.players.size()
				);
		}
		p.setName(value.toString());
		fireTableCellUpdated(row, col);
	}
}
